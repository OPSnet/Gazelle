<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TorrentsRequestsArtistsCleanup extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_artists')
             ->removeColumn('ArtistID')
             ->changePrimaryKey(['GroupID', 'Importance', 'AliasID'])
             ->addIndex('GroupID')
             ->addForeignKey('AliasID', 'artists_alias', 'AliasID')
             ->changeColumn('UserID', 'integer', ['null' => true])
             ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->query("
            UPDATE torrents_artists SET UserID = NULL WHERE UserID = 0
        ");
        $this->table('torrents_artists')
             ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
             ->save();
        $this->table('requests_artists')
             ->removeColumn('ArtistID')
             ->changePrimaryKey(['RequestID', 'Importance', 'AliasID'])
             ->addIndex('RequestID')
             ->addColumn('UserID', 'integer', ['null' => true])
             ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
             ->addForeignKey('AliasID', 'artists_alias', 'AliasID')
             ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
             ->save();
    }

    public function down(): void {
        $this->table('torrents_artists')
             ->dropForeignKey('UserID')
             ->save();
        $this->query("
            UPDATE torrents_artists SET UserID = 0 WHERE UserID = NULL
        ");
        $this->table('torrents_artists')
             ->addColumn('ArtistID', 'integer', ['null' => false])
             ->changeColumn('UserID', 'integer', ['null' => false, 'default' => 0])
             ->removeIndex('GroupID')
             ->removeColumn('created')
             ->dropForeignKey('UserID')
             ->dropForeignKey('AliasID')
             ->save();
        $this->query("
            UPDATE torrents_artists LEFT JOIN artists_alias USING (AliasID) SET
              torrents_artists.ArtistID = artists_alias.ArtistID
        ");
        $this->table('torrents_artists')
             ->changePrimaryKey(['GroupID', 'Importance', 'ArtistID'])
             ->save();
        $this->table('requests_artists')
             ->addColumn('ArtistID', 'integer', ['null' => false])
             ->dropForeignKey('AliasID')
             ->removeColumn('UserID')
             ->removeColumn('created')
             ->removeIndex('RequestID')
             ->save();
        $this->query("
            UPDATE requests_artists LEFT JOIN artists_alias USING (AliasID) SET
              requests_artists.ArtistID = artists_alias.ArtistID
        ");
        $this->table('requests_artists')
             ->changePrimaryKey(['RequestID', 'AliasID'])
             ->save();
    }
}
