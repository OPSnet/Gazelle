<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InvitePendingSourceNoUser extends AbstractMigration {
    public function up(): void {
        $this->table('invite_source_pending')
            ->dropForeignKey('user_id')
            ->removeColumn('user_id')
            ->save();
    }

    public function down(): void {
        $this->table('invite_source_pending')
            ->addColumn('user_id', 'integer')
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }
}
