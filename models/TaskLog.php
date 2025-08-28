<?php

namespace app\models;

use yii\db\ActiveRecord;

class TaskLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%task_log}}';
    }
}
