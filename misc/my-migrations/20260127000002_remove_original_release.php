<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveOriginalRelease extends AbstractMigration {
    public function up(): void {
        $this->query("
            UPDATE torrents
            INNER JOIN torrents_group AS tg ON (GroupID = tg.ID)
            SET
                RemasterYear = tg.Year,
                RemasterRecordLabel = COALESCE(tg.RecordLabel, ''),
                RemasterCatalogueNumber = COALESCE(tg.CatalogueNumber, '')
            WHERE Remastered = '0' AND tg.CategoryID = 1
        ");
        $this->query("
            UPDATE deleted_torrents
            LEFT JOIN torrents_group AS tg ON (GroupID = tg.ID)
            LEFT JOIN deleted_torrents_group AS del ON (GroupID = del.ID)
            SET
                RemasterYear = COALESCE(tg.Year, del.Year),
                RemasterRecordLabel = COALESCE(tg.RecordLabel, del.RecordLabel, ''),
                RemasterCatalogueNumber = COALESCE(tg.CatalogueNumber, del.CatalogueNumber, '')
            WHERE Remastered = '0' AND (tg.CategoryID = 1 OR del.CategoryID = 1)
        ");
        $this->table('torrents_group')
            ->removeColumn('RecordLabel')
            ->removeColumn('CatalogueNumber')
            ->save();
        $this->table('torrents')
            ->removeColumn('Remastered')
            ->save();
        $this->table('deleted_torrents_group')
            ->removeColumn('RecordLabel')
            ->removeColumn('CatalogueNumber')
            ->save();
        $this->table('deleted_torrents')
            ->removeColumn('Remastered')
            ->save();
    }

    public function down(): void {
        // If you really need to roll this back you'll have to find a way to restore the "original release"
        // data yourself.
        $this->table('torrents_group')
            ->addColumn('CatalogueNumber', 'string', ['length' => 80, 'null' => true])
            ->addColumn('RecordLabel', 'string', ['length' => 80, 'null' => true])
            ->save();
        $this->table('torrents')
            ->addColumn('Remastered', 'enum', ['values' => ['0','1'], 'default' => '0'])
            ->save();
        $this->table('deleted_torrents_group')
            ->addColumn('CatalogueNumber', 'string', ['length' => 80, 'null' => true])
            ->addColumn('RecordLabel', 'string', ['length' => 80, 'null' => true])
            ->save();
        $this->table('deleted_torrents')
            ->addColumn('Remastered', 'enum', ['values' => ['0','1'], 'default' => '0'])
            ->save();
    }
}
