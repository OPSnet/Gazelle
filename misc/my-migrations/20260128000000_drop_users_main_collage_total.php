<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUsersMainCollageTotal extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')->removeColumn('collage_total')->save();
    }

    public function down(): void {
        // if you really need to roll this back permanently,
        // the column will need be initialized from user_has_ordinal
        $this->table('users_main')
            ->addColumn('collage_total', 'integer', ['default' => 0])
            ->save();
    }
}
