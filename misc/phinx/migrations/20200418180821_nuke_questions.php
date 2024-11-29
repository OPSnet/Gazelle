<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class NukeQuestions extends AbstractMigration
{
    public function up(): void {
        $this->table('staff_answers')->drop()->update();
        $this->table('user_questions')->drop()->update();
    }

    public function down(): void {
        $this->table('staff_answers', [
                'id' => false, 'primary_key' => ['QuestionID', 'UserID'],
            ])
            ->addColumn('QuestionID', 'integer', [
                'null' => false, 'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false, 'limit' => 10,
            ])
            ->addColumn('Answer', 'text', [
                'null' => true, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID', 'unique' => false,
            ])
            ->create();
        $this->table('user_questions', [
                'id' => false, 'primary_key' => ['ID'],
            ])
            ->addColumn('ID', 'integer', [
                'null' => false, 'limit' => 10, 'identity' => true,
            ])
            ->addColumn('Question', 'text', [
                'null' => false, 'limit' => MysqlAdapter::TEXT_MEDIUM,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false, 'limit' => 10,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['Date'], [
                'name' => 'Date', 'unique' => false,
            ])
            ->create();
    }
}
