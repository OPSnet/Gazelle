<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDefaultSearch extends AbstractMigration {
    public function up(): void {
        $this->table('user_default_search', ['id' => false, 'primary_key' => 'id_user'])
            ->addColumn('id_user', 'integer', ['identity' => true])
            ->addColumn('search', 'text')
            ->save();
    }

    public function down(): void {
        $this->table('user_default_search')->drop()->save();
    }
}
