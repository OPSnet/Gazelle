<?php

use Phinx\Migration\AbstractMigration;

class IrcChannels extends AbstractMigration
{
    public function change(): void {
        $this->table('irc_channels', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('Name', 'string', ['limit' => 50])
            ->addColumn('Sort', 'integer', ['limit' => 11, 'default' => 0])
            ->addColumn('MinLevel', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
            ->addColumn('Classes', 'string', ['limit' => 100, 'default' => ''])
            ->addIndex('Name', ['unique' => true])
            ->create();
    }
}
