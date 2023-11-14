<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropIrcChannels extends AbstractMigration {
    public function up(): void {
        $this->table('irc_channels')->drop()->save();
    }

    public function down(): void {
        $this->table('irc_channels', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['identity' => true])
            ->addColumn('Name', 'string', ['limit' => 50])
            ->addColumn('Sort', 'integer', ['default' => 0])
            ->addColumn('MinLevel', 'integer', ['default' => 0])
            ->addColumn('Classes', 'string', ['limit' => 100, 'default' => ''])
            ->addIndex('Name', ['unique' => true])
            ->create();
    }
}
