<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DropUsersMainClass extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')
            ->removeColumn('Class')
            ->save();
    }

    public function down(): void {
        $this->table('users_main')
            ->addColumn('Class', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->save();
    }
}
