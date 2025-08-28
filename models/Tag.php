<?php

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property string $name */
class Tag extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tag}}';
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 64],
            [['name'], 'unique'],
        ];
    }

    public function getTasks()
    {
        return $this->hasMany(Task::class, ['id' => 'task_id'])
            ->viaTable('{{%task_tag}}', ['tag_id' => 'id']);
    }
}