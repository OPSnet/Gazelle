<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDownFkey extends AbstractMigration {
    public function up(): void {
        $this->query("
            DELETE ud
            FROM users_downloads ud
            LEFT JOIN torrents t ON (t.ID = ud.TorrentID)
            WHERE t.ID IS NULL
        ");
        $this->query("
            DELETE ud
            FROM users_downloads ud
            LEFT JOIN users_main um ON (um.ID = ud.UserID)
            WHERE um.ID IS NULL
        ");
        $this->table('users_downloads')
            ->addForeignKey('TorrentID', 'torrents', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('users_downloads')
            ->dropForeignKey('TorrentID')
            ->dropForeignKey('UserID')
            ->save();
    }
}
