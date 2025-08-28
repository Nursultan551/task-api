<?php

use yii\db\Migration;

class m250825_162737_create_tag_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%tag}}', [
            'id'   => $this->primaryKey(),
            'name' => $this->string(64)->notNull()->unique(),
        ]);

        $this->createTable('{{%task_tag}}', [
            'task_id' => $this->integer()->notNull(),
            'tag_id'  => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_task_tag', '{{%task_tag}}', ['task_id','tag_id']);

        $this->addForeignKey('fk_task_tag_task', '{{%task_tag}}', 'task_id', '{{%task}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_tag_tag',  '{{%task_tag}}', 'tag_id',  '{{%tag}}',  'id', 'CASCADE', 'CASCADE');
    
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%task_tag}}');
        $this->dropTable('{{%tag}}');
    }
}
