<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;

final class DeletedTorrentGroup extends AbstractMigration
{
    public function up(): void
    {
        $this->table('deleted_torrents_group', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['length' => 10, 'identity' => true])
            ->addColumn('CategoryID', 'integer', ['length' => 3])
            ->addColumn('Name', 'string', ['length' => 300])
            ->addColumn('Year', 'integer', ['length' => 4, 'null' => true])
            ->addColumn('CatalogueNumber', 'string', ['length' => 80, 'null' => true])
            ->addColumn('RecordLabel', 'string', ['length' => 80, 'null' => true])
            ->addColumn('ReleaseType', 'integer', ['length' => 2, 'default' => 21, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('TagList', 'string', ['length' => 500, 'default' => ''])
            ->addColumn('Time', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('RevisionID', 'integer', ['length' => 12, 'null' => true])
            ->addColumn('WikiBody', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('WikiImage', 'string', ['length' => 255])
            ->addColumn('VanityHouse', 'integer', ['length' => 1, 'default' => 0, 'limit' => MysqlAdapter::INT_TINY])
            ->save();

        // because phinx does not implement 'encoding' for tables
        // and doing the encoding now wipes the collation columns definitions, so do it all here
        // and phinx also does not honor lengths on tinyints
        // and it wants to map TEXT_MEDIUM to longtext
        $this->query("ALTER TABLE deleted_torrents_group
            CONVERT TO CHARACTER SET utf8mb4, COLLATE utf8mb4_unicode_ci,
            MODIFY ReleaseType tinyint(2) DEFAULT 21,
            MODIFY VanityHouse tinyint(1) DEFAULT 0,
            MODIFY WikiBody mediumtext
        ");
    }
    public function down(): void
    {
        // because "You can't delete all columns with ALTER TABLE; use DROP TABLE instead"
        $this->table('deleted_torrents_group')->drop()->save();
    }
}
