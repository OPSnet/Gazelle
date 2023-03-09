<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class DiscogsArtist extends AbstractMigration {
    public function change(): void {
        $this->table('artist_discogs', ['id' => false,  'primary_key' => 'artist_discogs_id'])
            ->addColumn('artist_discogs_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('artist_id',         'integer',  ['limit' => 10])
            ->addColumn('user_id',           'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('created',           'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('is_preferred',      'boolean',  ['default' => false])
            ->addColumn('sequence',          'integer',  ['signed' => false, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('stem',              'string',   ['limit' => 100, 'encoding' => 'utf8mb4'])
            ->addColumn('name',              'string',   ['limit' => 100, 'encoding' => 'utf8mb4'])
            ->addIndex('artist_id', ['unique' => true])
            ->addIndex('name',      ['unique' => true])
            ->addForeignKey('artist_id', 'artists_group', 'ArtistID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id',    'users_main',   'ID',       ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
