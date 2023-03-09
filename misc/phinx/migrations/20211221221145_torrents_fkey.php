<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TorrentsFkey extends AbstractMigration
{
    public function up(): void
    {
        $this->table('torrents')
            ->changeColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addForeignKey('GroupID', 'torrents_group', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->removeIndex(['Media'])
            ->save();
    }

    public function down(): void
    {
        $this->table('torrents')
            ->dropForeignKey('GroupID')
            ->dropForeignKey('UserID')
            ->addIndex(['Media'])
            ->save();
        $this->table('torrents')
            ->changeColumn('UserID', 'integer', ['limit' => 10, 'signed' => true])
            ->save();
    }
}
