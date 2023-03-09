<?php


use Phinx\Migration\AbstractMigration;

class TorrentStatsTables extends AbstractMigration {
    public function up(): void {
        $this->table('users_leech_stats', ['id' => false, 'primary_key' => 'UserID'])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('Uploaded', 'biginteger', ['limit' => 20, 'signed' => false, 'default' => 0])
            ->addColumn('Downloaded', 'biginteger', ['limit' => 20, 'signed' => false, 'default' => 0])
            ->addIndex(['Uploaded'], ['name' => 'uls_uploaded_idx'])
            ->addIndex(['Downloaded'], ['name' => 'uls_downloaded_idx'])
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('torrents_leech_stats', ['id' => false, 'primary_key' => 'TorrentID'])
            ->addColumn('TorrentID', 'integer', ['limit' => 10])
            ->addColumn('Seeders', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Leechers', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Snatched', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Balance', 'biginteger', ['limit' => 20, 'default' => 0])
            ->addColumn('last_action', 'datetime', ['null' => true])
            ->addIndex(['Seeders'], ['name' => 'tls_seeders_idx'])
            ->addIndex(['Leechers'], ['name' => 'tls_leechers_idx'])
            ->addIndex(['Snatched'], ['name' => 'tls_snatched_idx'])
            ->addIndex(['last_action'], ['name' => 'tls_last_action_idx'])
            ->addForeignKey('TorrentID', 'torrents', 'ID', ['delete' => 'CASCADE'])
            ->create();

        $this->table('deleted_torrents_leech_stats', ['id' => false, 'primary_key' => 'TorrentID'])
            ->addColumn('TorrentID', 'integer', ['limit' => 10])
            ->addColumn('Seeders', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Leechers', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Snatched', 'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('Balance', 'biginteger', ['limit' => 20, 'default' => 0])
            ->addColumn('last_action', 'datetime', ['null' => true])
            ->addForeignKey('TorrentID', 'deleted_torrents', 'ID', ['delete' => 'CASCADE'])
            ->create();

        $this->execute("INSERT INTO torrents_leech_stats (TorrentID, Seeders, Leechers, Snatched, Balance, last_action) SELECT ID, Seeders, Leechers, Snatched, balance, last_action FROM torrents");
        $this->execute("INSERT INTO users_leech_stats (UserID, Uploaded, Downloaded) SELECT ID, Uploaded, Downloaded FROM users_main");
    }

    public function down(): void {
        $this->table('users_leech_stats')->drop()->update();
        $this->table('torrents_leech_stats')->drop()->update();
        $this->table('deleted_torrents_leech_stats')->drop()->update();
    }
}
