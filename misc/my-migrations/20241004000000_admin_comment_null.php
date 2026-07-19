<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AdminCommentNull extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')
             ->changeColumn('AdminComment', 'text', ['limit' => 65536, 'null' => true])
             ->save();
    }

    public function down(): void {
        $this->table('users_info')
             ->changeColumn('AdminComment', 'text', ['limit' => 65536])
             ->save();
    }
}
