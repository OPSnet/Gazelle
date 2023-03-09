<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserSessionsDropUnused extends AbstractMigration
{
    public function up(): void {
        $this->table('users_sessions')
            ->removeIndex(['Active'])
            ->removeIndex(['UserID'])
            ->update();
    }

    public function down(): void {
        $this->table('users_sessions')
            ->addIndex(['Active'])
            ->addIndex(['UserID'])
            ->update();
    }
}
