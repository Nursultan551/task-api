<?php

namespace tests\unit\controllers;

use app\controllers\TaskController;
use Codeception\Test\Unit;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class TaskControllerTest extends Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        // reset query/body params before each test
        Yii::$app->request->setQueryParams([]);
        if (method_exists(Yii::$app->request, 'setBodyParams')) {
            Yii::$app->request->setBodyParams([]);
        }
    }

    private function getDataProvider(array $queryParams = []): ActiveDataProvider
    {
        Yii::$app->request->setQueryParams($queryParams);
        $controller = new TaskController('task', Yii::$app);
        $actions = $controller->actions();
        $prep = $actions['index']['prepareDataProvider'];
        /** @var ActiveDataProvider $dp */
        $dp = $prep(null);
        $this->assertInstanceOf(ActiveDataProvider::class, $dp);
        return $dp;
    }

    public function testDefaultSortIsCreatedAtDesc(): void
    {
        $dp = $this->getDataProvider([]);
        $this->assertArrayHasKey('created_at', $dp->sort->defaultOrder);
        $this->assertSame(SORT_DESC, $dp->sort->defaultOrder['created_at']);
    }

    public function testPrioritySortAttributePresent(): void
    {
        $dp = $this->getDataProvider(['sort' => 'priority']);
        $this->assertArrayHasKey('priority', $dp->sort->attributes);
        $attr = $dp->sort->attributes['priority'];
        // Should be configured with custom asc/desc (Expression or array)
        $this->assertArrayHasKey('asc', $attr);
        $this->assertArrayHasKey('desc', $attr);
    }

    public function testActiveDefaultFiltersOutDeleted(): void
    {
    $dp = $this->getDataProvider([]);
    $this->assertInstanceOf(ActiveQuery::class, $dp->query);
    /** @var ActiveQuery $query */
    $query = $dp->query;
    $where = $query->where;
        // Default scope should apply deleted_at IS NULL
        $this->assertNotEmpty($where);
        // Depending on how conditions are combined, check contains deleted_at => null
        $found = $this->containsCondition($where, ['deleted_at' => null]);
        $this->assertTrue($found, 'Expected where to contain [deleted_at => null]');
    }

    public function testOnlyDeletedFlag(): void
    {
    $dp = $this->getDataProvider(['deleted' => 1]);
    $this->assertInstanceOf(ActiveQuery::class, $dp->query);
    /** @var ActiveQuery $query */
    $query = $dp->query;
    $where = $query->where;
        // Should be NOT ['deleted_at' => null]
        $this->assertEquals(['not', ['deleted_at' => null]], $where);
    }

    public function testWithDeletedFlag(): void
    {
    $dp = $this->getDataProvider(['with_deleted' => 1]);
    $this->assertInstanceOf(ActiveQuery::class, $dp->query);
    /** @var ActiveQuery $query */
    $query = $dp->query;
    $where = $query->where;
        // No deleted_at filter should be present
    $this->assertTrue($where === null || $where === []);
    }

    public function testStatusAndPriorityFiltersApplied(): void
    {
    $dp = $this->getDataProvider(['status' => 'pending', 'priority' => 'high']);
    $this->assertInstanceOf(ActiveQuery::class, $dp->query);
    /** @var ActiveQuery $query */
    $query = $dp->query;
    $where = $query->where;
        // Should include deleted_at IS NULL and both equality filters
        $this->assertTrue($this->containsCondition($where, ['deleted_at' => null]));
        $this->assertTrue($this->containsCondition($where, ['status' => 'pending']));
        $this->assertTrue($this->containsCondition($where, ['priority' => 'high']));
    }

    private function containsCondition($where, array $needle): bool
    {
        if ($where === null) return false;
        if ($where === $needle) return true;
        if (!is_array($where)) return false;
        // Operator format: ['and', cond1, cond2, ...]
        foreach ($where as $k => $v) {
            if ($k === 0 && is_string($v)) continue;
            if ($v === $needle) return true;
            if (is_array($v) && $this->containsCondition($v, $needle)) return true;
        }
        return false;
    }
}
