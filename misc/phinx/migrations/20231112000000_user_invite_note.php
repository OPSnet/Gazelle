<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserInviteNote extends AbstractMigration {
    public function up(): void {
        $this->table('invites')->addColumn('Notes', 'string', ['length' => 2048, 'default' => ''])->save();
    }

    public function down(): void {
        $this->table('invites')->removeColumn('Notes')->save();
    }
}
