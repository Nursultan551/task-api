<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%task}}`.
 */
class m250825_162253_create_task_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%task}}', [
            'id'          => $this->primaryKey(),
            'title'       => $this->string()->notNull(),
            'description' => $this->text(),
            'status'      => $this->string(20)->notNull()->defaultValue('pending'),
            'priority'    => $this->string(20)->notNull()->defaultValue('medium'),
            'due_date'    => $this->date()->null(),
            'created_at'  => $this->integer()->notNull(),
            'updated_at'  => $this->integer()->notNull(),
            'deleted_at'  => $this->integer()->null(),
        ]);

        // helpful indexes
        $this->createIndex('idx_task_status',   '{{%task}}', 'status');
        $this->createIndex('idx_task_priority', '{{%task}}', 'priority');
        $this->createIndex('idx_task_due_date', '{{%task}}', 'due_date');
        $this->execute("ALTER TABLE {{%task}} ADD CONSTRAINT chk_task_status CHECK (status IN ('pending','in_progress','completed'))");
        $this->execute("ALTER TABLE {{%task}} ADD CONSTRAINT chk_task_priority CHECK (priority IN ('low','medium','high'))");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%task}}');
    }
}
