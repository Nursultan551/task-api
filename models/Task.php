<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $title
 * @property string|null $description
 * @property string      $status
 * @property string      $priority
 * @property string|null $due_date
 * @property int         $created_at
 * @property int         $updated_at
 * @property int|null    $deleted_at
 */
class Task extends ActiveRecord
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';

    public static function tableName(): string
    {
        return '{{%task}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            // normalize input
            [['title', 'description'], 'filter', 'filter' => 'trim'],
            [['title'], 'required'],
            [['title'], 'string', 'min' => 5, 'max' => 255],
            [['description'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED]],
            [['priority'], 'in', 'range' => [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH]],
            [['due_date'], 'date', 'format' => 'php:Y-m-d'],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['priority'], 'default', 'value' => self::PRIORITY_MEDIUM],
        ];
    }

    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('{{%task_tag}}', ['task_id' => 'id']);
    }

    public function softDelete(): bool
    {
        $this->deleted_at = time();
        return $this->save(false, ['deleted_at', 'updated_at']);
    }

    public function restore(): bool
    {
        $this->deleted_at = null;
        return $this->save(false, ['deleted_at', 'updated_at']);
    }

    public static function find(): TaskQuery
    {
        return new TaskQuery(static::class);
    }

    /**
     * Expose useful fields for API responses.
     */
    public function fields(): array
    {
        $fields = parent::fields();
        // add tags (names) and tag_ids to the response for convenience
        $fields['tags'] = function (self $model) {
            return array_values(array_map(static function (Tag $t) {
                return $t->name;
            }, $model->tags));
        };
        $fields['tag_ids'] = function (self $model) {
            return array_values(array_map(static function (Tag $t) {
                return (int)$t->id;
            }, $model->tags));
        };
        return $fields;
    }

    /**
     * Sync task tags with provided list of IDs or names.
     * @param array<int|string> $tags List like [1,2] or ['bug','feature']
     */
    public function syncTags(array $tags): void
    {
        // normalize to IDs, creating tags by name if needed
        $ids = [];
        foreach ($tags as $tag) {
            if ($tag === null || $tag === '') {
                continue;
            }
            if (is_numeric($tag)) {
                $ids[] = (int)$tag;
            } else {
                $name = trim((string)$tag);
                if ($name === '') {
                    continue;
                }
                $model = Tag::findOne(['name' => $name]);
                if (!$model) {
                    $model = new Tag(['name' => $name]);
                    $model->save(false);
                }
                $ids[] = (int)$model->id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        // current ids
        $current = array_map(static function (Tag $t) { return (int)$t->id; }, $this->tags);
        sort($current);
        sort($ids);

        $toInsert = array_diff($ids, $current);
        $toDelete = array_diff($current, $ids);

        $db = static::getDb();
        if ($toDelete) {
            $db->createCommand()
                ->delete('{{%task_tag}}', ['task_id' => $this->id, 'tag_id' => $toDelete])
                ->execute();
        }
        if ($toInsert) {
            $rows = [];
            foreach ($toInsert as $id) {
                $rows[] = [$this->id, (int)$id];
            }
            $db->createCommand()
                ->batchInsert('{{%task_tag}}', ['task_id', 'tag_id'], $rows)
                ->execute();
        }
        // refresh relation cache
        $this->populateRelation('tags', Tag::find()->innerJoin('{{%task_tag}} tt', 'tt.tag_id = {{%tag}}.id')
            ->andWhere(['tt.task_id' => $this->id])->all());
    }
}
