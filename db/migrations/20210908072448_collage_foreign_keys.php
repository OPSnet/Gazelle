<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CollageForeignKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->table('collages')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('collages_artists')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('CollageID', 'collages', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('ArtistID', 'artists_group', 'ArtistID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('collages_torrents')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('CollageID', 'collages', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('GroupID', 'torrents_group', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('bookmarks_collages')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('CollageID', 'collages', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('users_collage_subs')
            ->changeColumn('UserID', 'integer', ['signed' => false])
            ->addForeignKey('CollageID', 'collages', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void
    {

        $this->table('collages')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('collages_artists')
            ->dropForeignKey('CollageID')
            ->dropForeignKey('ArtistID')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('collages_torrents')
            ->dropForeignKey('CollageID')
            ->dropForeignKey('GroupID')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('bookmarks_collages')
            ->dropForeignKey('CollageID')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('users_collage_subs')
            ->dropForeignKey('CollageID')
            ->dropForeignKey('UserID')
            ->save();

        $this->table('collages')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();

        $this->table('collages_artists')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();

        $this->table('collages_torrents')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();

        $this->table('bookmarks_collages')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();

        $this->table('users_collage_subs')
            ->changeColumn('UserID', 'integer', ['signed' => true])
            ->save();
    }
}
