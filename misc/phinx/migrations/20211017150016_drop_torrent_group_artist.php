<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

final class DropTorrentGroupArtist extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('torrents_group')
            ->removeColumn('ArtistId')
            ->changeColumn('CategoryID', 'integer', ['length' => 3, 'null' => false])
            ->changeColumn('Name', 'string', ['length' => 300, 'null' => false])
            ->save();
        $this->query('ALTER TABLE torrents_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('ArtistID', 'integer', ['limit' => 10, 'default' => 0])
            ->changeColumn('CategoryID', 'integer', ['length' => 3, 'null' => true])
            ->changeColumn('Name', 'string', ['length' => 300, 'null' => true])
            ->save();
        $this->query('ALTER TABLE torrents_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
    }
}
