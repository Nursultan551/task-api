<?php

namespace app\controllers;

use app\models\Task;
use app\models\TaskLog;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\rest\ActiveController;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\db\Expression;
use yii\filters\auth\HttpBearerAuth;

class TaskController extends ActiveController
{
    public $modelClass = Task::class;

    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        // Make API stateless: no PHP session/cookie auth fallback
        if (isset(\Yii::$app->user)) {
            \Yii::$app->user->enableSession = false;
            \Yii::$app->user->loginUrl = null;
        }
        // Allow CORS
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Request-Headers' => ['*'],
            ],
        ];

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => ['application/json' => Response::FORMAT_JSON],
        ];

        // Simple Bearer auth (hardcoded token handled via User::findIdentityByAccessToken)
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['options'],
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'index'         => ['GET', 'HEAD'],
                'view'          => ['GET', 'HEAD'],
                'create'        => ['POST'],
                'update'        => ['PUT', 'PATCH'],
                'delete'        => ['DELETE'],
                'toggle-status' => ['PATCH'],
                'restore'       => ['PATCH'],
            ],
        ];

        return $behaviors;
    }

    // Filtering + pagination + sorting
    public function actions()
    {
        $actions = parent::actions();
        // override the data provider for index
        $actions['index']['prepareDataProvider'] = function ($action) {
            $request = Yii::$app->request;

            // Control soft-deleted visibility
            $deleted = $request->get('deleted');
            $withDeleted = $request->get('with_deleted');
            $isTruthy = static function ($v): bool {
                if ($v === null) return false;
                if ($v === true) return true;
                if ($v === false) return false;
                if (is_numeric($v)) return ((int)$v) === 1;
                if (is_string($v)) return in_array(strtolower($v), ['1','true','yes','on'], true);
                return false;
            };

            if ($isTruthy($deleted)) {
                $query = Task::find()->andWhere(['not', ['deleted_at' => null]]);
            } elseif ($isTruthy($withDeleted)) {
                $query = Task::find();
            } else {
                $query = Task::find()->active();
            }

            // Filters
            $status   = $request->get('status');
            $priority = $request->get('priority');
            $from     = $request->get('due_date_from');
            $to       = $request->get('due_date_to');
            $keyword  = $request->get('q');

            if ($status) {
                $query->andWhere(['status' => $status]);
            }
            if ($priority) {
                $query->andWhere(['priority' => $priority]);
            }
            if ($from) {
                $query->andWhere(['>=', 'due_date', $from]);
            }
            if ($to) {
                $query->andWhere(['<=', 'due_date', $to]);
            }
            if ($keyword) {
                $query->andWhere(['like', 'title', $keyword]);
            }

            // Filter by tag (bonus)
            $tag = $request->get('tag'); // tag name or id
            if ($tag !== null && class_exists(\app\models\Tag::class)) {
                $query->joinWith('tags t');
                if (is_numeric($tag)) {
                    $query->andWhere(['t.id' => (int)$tag]);
                } else {
                    $query->andWhere(['t.name' => $tag]);
                }
                $query->distinct();
            }

            // Pagination (limit/page)
            $limit = (int)$request->get('limit', 20);
            $limit = max(1, min($limit, 100)); // cap at 100
            $page  = (int)$request->get('page', 1);

            // Sorting (supports: created_at, due_date, priority)
            // Custom priority order: low < medium < high
            $priorityAsc = new Expression("(CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 ELSE 4 END) ASC");
            $priorityDesc = new Expression("(CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 ELSE 4 END) DESC");
            $sortAttributes = [
                'created_at',
                'due_date',
                'priority' => [
                    'asc' => $priorityAsc,
                    'desc' => $priorityDesc,
                    'default' => SORT_ASC,
                ],
            ];

            return new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'pageSize' => $limit,
                    'pageParam' => 'page',
                    'pageSizeParam' => 'limit',
                    'validatePage' => true,
                    'defaultPageSize' => 20,
                ],
                'sort' => [
                    'attributes' => $sortAttributes,
                    'defaultOrder' => ['created_at' => SORT_DESC],
                ],
            ]);
        };
    // use our inline actions for create/update/delete
    unset($actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    // Override create to set status code 201, handle tags, and audit log
    public function actionCreate()
    {
        $model = new Task();
        $model->load(Yii::$app->getRequest()->getBodyParams(), '');
        $tags = Yii::$app->request->getBodyParam('tags');
        $tagIds = Yii::$app->request->getBodyParam('tag_ids');

        if ($model->save()) {
            if (is_array($tags)) {
                $model->syncTags($tags);
            } elseif (is_array($tagIds)) {
                $model->syncTags($tagIds);
            }
            $this->log($model->id, 'create', json_encode($model->attributes));
            Yii::$app->response->statusCode = 201;
            return $model;
        }
        Yii::$app->response->statusCode = 422;
        return $model->errors;
    }

    // Override update to handle tags and audit log
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $old = $model->attributes;
        $model->load(Yii::$app->getRequest()->getBodyParams(), '');
        $tags = Yii::$app->request->getBodyParam('tags');
        $tagIds = Yii::$app->request->getBodyParam('tag_ids');
        if ($model->save()) {
            if (is_array($tags)) {
                $model->syncTags($tags);
            } elseif (is_array($tagIds)) {
                $model->syncTags($tagIds);
            }
            $changes = [];
            foreach ($model->attributes as $k => $v) {
                if (($old[$k] ?? null) !== $v) {
                    $changes[$k] = ['from' => $old[$k] ?? null, 'to' => $v];
                }
            }
            $this->log($model->id, 'update', $changes ? json_encode($changes) : null);
            return $model;
        }
        Yii::$app->response->statusCode = 422;
        return $model->errors;
    }

    // Soft delete instead of hard delete
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->softDelete()) {
            $this->log($model->id, 'delete', null);
            Yii::$app->response->statusCode = 200;
            return ['message' => 'Deleted'];
        }
        Yii::$app->response->statusCode = 500;
        return ['message' => 'Failed to delete'];
    }

    // Bonus: toggle status endpoint
    public function actionToggleStatus($id)
    {
        $model = $this->findModel($id);
        $old = $model->status;
        $map = [
            Task::STATUS_PENDING     => Task::STATUS_IN_PROGRESS,
            Task::STATUS_IN_PROGRESS => Task::STATUS_COMPLETED,
            Task::STATUS_COMPLETED   => Task::STATUS_PENDING,
        ];
        $model->status = $map[$model->status] ?? Task::STATUS_PENDING;

        if ($model->save()) {
            $this->log($model->id, 'toggle', json_encode(['from' => $old, 'to' => $model->status]));
            return $model;
        }
        Yii::$app->response->statusCode = 422;
        return $model->errors;
    }

    // Bonus: restore soft-deleted
    public function actionRestore($id)
    {
        $model = Task::find()->andWhere(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException('Task not found');
        }
        if ($model->deleted_at === null) {
            return ['message' => 'Nothing to restore'];
        }
        if ($model->restore()) {
            $this->log($model->id, 'restore', null);
            return $model;
        }
        Yii::$app->response->statusCode = 500;
        return ['message' => 'Restore failed'];
    }

    protected function findModel($id): Task
    {
        $model = Task::find()->active()->andWhere(['id' => (int)$id])->one();
        if (!$model) {
            throw new NotFoundHttpException('Task not found');
        }
        return $model;
    }

    private function log(int $taskId, string $action, ?string $changes): void
    {
        if (!class_exists(\app\models\TaskLog::class)) {
            return;
        }
        $log = new TaskLog();
        $log->task_id = $taskId;
        $log->action = $action;
        $log->changes = $changes;
        $log->created_at = time();
        $log->save(false);
    }
}
