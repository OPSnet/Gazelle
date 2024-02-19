<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MoreTableDefaults extends AbstractMigration
{
    public function up(): void
    {
        $this->table('forums_topics')
            ->changeColumn('Title', 'string', ['length' => 255, 'null' => false])
            ->save();
        $this->table('forums_posts')
            ->changeColumn('EditedUserID', 'integer', ['length' => 10, 'signed' => false, 'null' => true])
            ->save();
        $this->table('login_attempts')
            ->changeColumn('Bans', 'integer', ['null' => false, 'default' => 0])
            ->save();
        $this->query("
            ALTER TABLE sphinx_requests_delta MODIFY ArtistList mediumtext
        ");
        $this->query("
            ALTER TABLE sphinx_requests
                MODIFY COLUMN ArtistList mediumtext NULL,
                MODIFY COLUMN CatalogueNumber varchar(50) NOT NULL DEFAULT ''
        ");
        $this->query("
            ALTER TABLE sphinx_t
                MODIFY COLUMN freetorrent tinyint NOT NULL DEFAULT 0,
                MODIFY COLUMN media varchar(15) NOT NULL DEFAULT '',
                MODIFY COLUMN format varchar(15) NOT NULL DEFAULT '',
                MODIFY COLUMN remyear smallint
        ");
        $this->table('torrents')
            ->changeColumn('Media', 'string', ['length' => 20, 'null' => false, 'default' => ''])
            ->save();
    }

    public function down(): void
    {
        $this->table('forums_topics')
            ->changeColumn('Title', 'string', ['length' => 255, 'null' => true])
            ->save();
        $this->table('forums_posts')
            ->changeColumn('EditedUserID', 'integer', ['length' => 10, 'signed' => false, 'null' => false])
            ->save();
        $this->table('login_attempts')
            ->changeColumn('Bans', 'integer', ['null' => false])
            ->save();
        $this->table('sphinx_requests_delta')
            ->changeColumn('ArtistList', 'string', ['length' => 2048, 'null' => true])
            ->save();
        $this->query("
            ALTER TABLE sphinx_requests
                MODIFY COLUMN ArtistList varchar(2048) DEFAULT NULL,
                MODIFY COLUMN CatalogueNumber varchar(50) NOT NULL,
        ");
        $this->query("
            ALTER TABLE sphinx_t
                MODIFY COLUMN freetorrent tinyint NOT NULL DEFAULT 0,
                MODIFY COLUMN media varchar(15) NOT NULL DEFAULT '',
                MODIFY COLUMN format varchar(15) NOT NULL DEFAULT '',
                MODIFY COLUMN remyear smallint NOT NULL
        ");
        $this->table('torrents')
            ->changeColumn('Media', 'string', ['length' => 20, 'null' => true])
            ->save();
    }
}
