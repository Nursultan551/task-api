<?php

use yii\db\Migration;

class m250825_163041_create_task_log extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%task_log}}', [
            'id'         => $this->primaryKey(),
            'task_id'    => $this->integer()->notNull(),
            'action'     => $this->string(32)->notNull(), // create/update/delete/restore/toggle
            'changes'    => $this->text()->null(),        // JSON encoded
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_task_log_task_id', '{{%task_log}}', 'task_id');
        $this->addForeignKey('fk_task_log_task', '{{%task_log}}', 'task_id', '{{%task}}', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%task_log}}');
    }
}
