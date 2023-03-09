<?php

use Phinx\Migration\AbstractMigration;

class AttributeTables extends AbstractMigration {
    public function up(): void {
        // alter table collages modify id int(10) unsigned not null;
        $this->table('collage_attr', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 6, 'identity' => true])
             ->addColumn('Name', 'string', ['limit' => 24])
             ->addColumn('Description', 'string', ['limit' => 500])
             ->addIndex(['Name'], ['unique' => true])
             ->create();

        $this->table('collage_has_attr', ['id' => false, 'primary_key' => ['CollageID', 'CollageAttrID' ]])
            ->addColumn('CollageID', 'integer', ['limit' => 10])
            ->addColumn('CollageAttrID', 'integer', ['limit' => 6])
            ->addIndex(['CollageAttrID'])
            ->addForeignKey('CollageID', 'collages', 'ID')
            ->addForeignKey('CollageAttrID', 'collage_attr', 'ID')
            ->create();

        $this->table('torrent_group_attr', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 6, 'identity' => true])
             ->addColumn('Name', 'string', ['limit' => 24])
             ->addColumn('Description', 'string', ['limit' => 500])
             ->addIndex(['Name'], ['unique' => true])
             ->create();

        $this->table('torrent_group_has_attr', ['id' => false, 'primary_key' => ['TorrentGroupID', 'TorrentGroupAttrID']])
            ->addColumn('TorrentGroupID', 'integer', ['limit' => 10])
            ->addColumn('TorrentGroupAttrID', 'integer', ['limit' => 6])
            ->addIndex(['TorrentGroupAttrID'])
            ->addForeignKey('TorrentGroupAttrID', 'torrent_group_attr', 'ID')
            ->addForeignKey('TorrentGroupID', 'torrents_group', 'ID')
            ->create();

        $this->table('torrent_attr', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 6, 'identity' => true])
             ->addColumn('Name', 'string', ['limit' => 24])
             ->addColumn('Description', 'string', ['limit' => 500])
             ->addIndex(['Name'], ['unique' => true])
             ->create();

        $this->table('torrent_has_attr', ['id' => false, 'primary_key' => ['TorrentID', 'TorrentAttrID']])
            ->addColumn('TorrentID', 'integer', ['limit' => 10])
            ->addColumn('TorrentAttrID', 'integer', ['limit' => 6])
            ->addIndex(['TorrentAttrID'])
            ->addForeignKey('TorrentAttrID', 'torrent_attr', 'ID')
            ->addForeignKey('TorrentID', 'torrents', 'ID')
            ->create();

        $this->table('user_attr', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 6, 'identity' => true])
             ->addColumn('Name', 'string', ['limit' => 24])
             ->addColumn('Description', 'string', ['limit' => 500])
             ->addIndex(['Name'], ['unique' => true])
             ->create();

        $this->table('user_has_attr', ['id' => false, 'primary_key' => ['UserID', 'UserAttrID']])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false]) // Gazelle consistency ftw
            ->addColumn('UserAttrID', 'integer', ['limit' => 6])
            ->addIndex(['UserAttrID'])
            ->addForeignKey('UserAttrID', 'user_attr', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('collage_attr')
             ->insert([
                ['Name' => 'sort-newest', 'Description' => 'New additions appear at the top of list']
             ])
             ->save();

        $this->table('torrent_group_attr')
             ->insert([
                ['Name' => 'no-cover-art', 'Description' => 'This release has no official artwork']
             ])
             ->save();

        $this->table('user_attr')
             ->insert([
                ['Name' => 'no-fl-gifts', 'Description' => 'This user does not want to receive FL token gifts']
             ])
             ->save();
    }

    public function down(): void {
        $this->table('collage_has_attr')->drop()->save();
        $this->table('collage_attr')->drop()->save();

        $this->table('torrent_group_has_attr')->drop()->save();
        $this->table('torrent_group_attr')->drop()->save();

        $this->table('torrent_has_attr')->drop()->save();
        $this->table('torrent_attr')->drop()->save();

        $this->table('user_has_attr')->drop()->save();
        $this->table('user_attr')->drop()->save();
    }
}
