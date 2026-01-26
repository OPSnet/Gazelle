<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUsersGeodistribution extends AbstractMigration {
    public function up(): void {
        $this->table('users_geodistribution')->drop()->save();
    }

    public function down(): void {
        $this->table('users_geodistribution', ['id' => false, 'primary_key' => 'Code'])
            ->addColumn('Code', 'string', ['limit' => 2])
            ->addColumn('Users', 'integer')
            ->save();
    }
}
