<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserAttrInactive extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')->insert([
            'Name' => 'inactive-warning-sent',
            'Description' => 'An email was sent to the user to inform them they will soon be deactivated',
        ])->save();

        $this->table('periodic_task')
             ->insert([
                [
                    'name'        => 'Inactive User Warn',
                    'classname'   => 'InactiveUserWarn',
                    'description' => 'Send a reminder email to inactive users',
                    'period'      => 7200,
                    'is_enabled'  => 1,
                ],[
                    'name'        => 'Inactive User Deactivate',
                    'classname'   => 'InactiveUserDeactivate',
                    'description' => 'Deactivate inactive users',
                    'period'      => 7200,
                    'is_enabled'  => 1,
                ],
            ])
            ->save();

        $this->query("
            UPDATE periodic_task SET is_enabled = 0 WHERE Name = 'User Reaper'
            ")->execute();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('user_attr')
            ->where(['Name' => 'inactive-warning-sent'])
            ->execute();

        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(fn($w) => $w->in('classname', [
                'InactiveUserWarn',
                'InactiveUserDeactivate',
            ]))
            ->execute();

        $this->query("
            UPDATE periodic_task SET is_enabled = 1 WHERE Name = 'User Reaper'
            ")->execute();
    }
}
