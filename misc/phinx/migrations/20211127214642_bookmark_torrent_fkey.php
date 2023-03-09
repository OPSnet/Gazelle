<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BookmarkTorrentFkey extends AbstractMigration
{
    public function up(): void
    {
        $this->table('bookmarks_torrents')
            ->changeColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addForeignKey('GroupID', 'torrents_group', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
    }

    public function down(): void
    {
        $this->table('bookmarks_torrents')
            ->dropForeignKey('GroupID')
            ->dropForeignKey('UserID')
            ->save();
        $this->table('bookmarks_torrents')
            ->changeColumn('UserID', 'integer', ['limit' => 10, 'signed' => true])
            ->save();
    }
}
