<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropLabelAliases extends AbstractMigration {
    public function up(): void {
        $this->table('label_aliases')->drop()->save();
    }

    public function down(): void {
        $this->table('label_aliases', ['id' => false, 'primary_key' => ['ID']])
            ->addColumn('ID', 'integer', ['null' => false, 'identity' => 'enable'])
            ->addColumn('BadLabel', 'string', ['limit' => 100])
            ->addColumn('AliasLabel', 'string', ['limit' => 100])
            ->addIndex(['BadLabel'], ['name' => 'BadLabel', 'unique' => false])
            ->addIndex(['AliasLabel'], ['name' => 'AliasLabel', 'unique' => false])
            ->create();
    }
}
