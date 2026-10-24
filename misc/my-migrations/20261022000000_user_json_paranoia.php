<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserJsonParanoia extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')
            ->addColumn('paranoia_v2', 'json', ['null' => true])
            ->save();
    }

    public function down(): void {
        $this->table('users_main')
            ->removeColumn('paranoia_v2')
            ->save();
    }
}
