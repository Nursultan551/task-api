<?php

namespace app\models;

use yii\db\ActiveQuery;

class TaskQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['deleted_at' => null]);
    }

    public function all($db = null)
    {
        return parent::all($db);
    }

    public function one($db = null)
    {
        return parent::one($db);
    }
}
