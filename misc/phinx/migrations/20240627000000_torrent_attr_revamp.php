<?php

use Phinx\Migration\AbstractMigration;

class TorrentAttrRevamp extends AbstractMigration {
    public function up(): void {
        $this->table('torrent_has_attr')
            ->addColumn('UserID', 'integer', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex('TorrentID')
            ->save();
        $this->table('deleted_torrent_has_attr', ['id' => false, 'primary_key' => ['TorrentID', 'TorrentAttrID']])
            ->addColumn('TorrentID', 'integer')
            ->addColumn('TorrentAttrID', 'integer')
            ->addColumn('UserID', 'integer', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('TorrentID', 'deleted_torrents', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('TorrentAttrID', 'torrent_attr', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex('TorrentID')
            ->save();
        $rows = [
            ['Name' => 'bad_files', 'Description' => 'bad file names'],
            ['Name' => 'bad_folders', 'Description' => 'bad folder names'],
            ['Name' => 'bad_tags', 'Description' => 'bad tags'],
            ['Name' => 'cassette_approved', 'Description' => 'approved cassette rip'],
            ['Name' => 'lossymaster_approved', 'Description' => 'approved lossy master'],
            ['Name' => 'lossyweb_approved', 'Description' => 'approved lossy WEB release'],
            ['Name' => 'missing_lineage', 'Description' => 'missing lineage information'],
            ['Name' => 'trumpable', 'Description' => 'trumpable for miscellaneous reasons'],
        ];
        $this->table('torrent_attr')->insert($rows)->save();
        foreach ($rows as $row) {
            if ($row['Name'] === 'trumpable') {
                continue;
            }
            $this->execute("
                INSERT INTO torrent_has_attr (TorrentAttrID, TorrentID, UserID, created)
                SELECT (SELECT ID FROM torrent_attr WHERE Name = '{$row['Name']}'),
                       TorrentID, UserID, TimeAdded
                FROM torrents_{$row['Name']}
            ");
            $this->execute("
                INSERT INTO deleted_torrent_has_attr (TorrentAttrID, TorrentID, UserID, created)
                SELECT (SELECT ID FROM torrent_attr WHERE Name = '{$row['Name']}'),
                       TorrentID, UserID, TimeAdded
                FROM deleted_torrents_{$row['Name']}
            ");
            $this->table("torrents_{$row['Name']}")->drop()->save();
            $this->table("deleted_torrents_{$row['Name']}")->drop()->save();
        }
    }

    public function down(): void {
        throw new \Exception("not implemented");
    }
}
