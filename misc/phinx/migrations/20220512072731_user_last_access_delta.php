<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserLastAccessDelta extends AbstractMigration {
    public function up(): void {
        $this->table('user_last_access_delta', ['id' => false, 'primary_key' => 'user_last_access_delta_id'])
            ->addColumn('user_last_access_delta_id', 'integer', ['identity' => true])
            ->addColumn('user_id', 'integer')
            ->addColumn('last_access', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'ulad_idx'])
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();

        $this->table('periodic_task')
             ->insert([
                 [
                     'name' => 'UserLastAccess',
                     'classname' => 'UserLastAccess',
                     'description' => 'Update user last access deltas',
                     'period' => 60 * 45,
                 ],
             ])
             ->save();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(function ($exp) {
                return $exp->in('classname', ['UserLastAccess']);
            })
            ->execute();

        // because foreign key
        $this->table('user_last_access_delta')->drop()->update();
    }
}
