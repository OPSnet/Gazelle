<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumTransitionDefaultValues extends AbstractMigration {
    public function up(): void {
        $this->table('forums_transitions')
            ->changeColumn('permissions', 'string', ['length' => 100, 'null' => false, 'default' => ''])
            ->changeColumn('user_ids',    'string', ['length' => 100, 'null' => false, 'default' => ''])
            ->save();
    }

    public function down(): void {
        $this->table('forums_transitions')
            ->changeColumn('permissions', 'string', ['length' => 100, 'null' => false])
            ->changeColumn('user_ids',    'string', ['length' => 100, 'null' => false])
            ->save();
    }
}
