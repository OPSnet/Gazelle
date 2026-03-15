<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameTgroupAttr extends AbstractMigration {
    public function up(): void {
        $this->table('tgroup_attr', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['identity' => true])
             ->addColumn('Name', 'string', ['limit' => 24])
             ->addColumn('Description', 'string', ['limit' => 500])
             ->addIndex(['Name'], ['unique' => true])
             ->create();

        $this->table('tgroup_has_attr', ['id' => false, 'primary_key' => ['TGroupID', 'TGroupAttrID']])
            ->addColumn('TGroupID', 'integer')
            ->addColumn('TGroupAttrID', 'integer')
            ->addIndex(['TGroupAttrID'])
            ->addForeignKey('TGroupAttrID', 'tgroup_attr', 'ID')
            ->addForeignKey('TGroupID', 'torrents_group', 'ID')
            ->create();

        // There is only one row at this point, so we can
        // gamble on the fact that the ID will be 1.
        $this->query('
            INSERT INTO tgroup_attr (Name, Description)
            SELECT Name, Description
            FROM torrent_group_attr
        ');
        $this->query('
            INSERT INTO tgroup_has_attr
            SELECT *
            FROM torrent_group_has_attr
        ');
    }

    public function down(): void {
        $this->table('tgroup_has_attr')->drop()->save();
        $this->table('tgroup_attr')->drop()->save();
    }
}
