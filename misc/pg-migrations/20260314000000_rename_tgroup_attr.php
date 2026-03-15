<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameTgroupAttr extends AbstractMigration {
    public function up(): void {
        $this->table('tgroup_has_attr', ['id' => false, 'primary_key' => ['id_tgroup', 'id_tgroup_attr']])
            ->addColumn('id_tgroup', 'integer')
            ->addColumn('id_tgroup_attr', 'integer')
            ->addIndex('id_tgroup_attr', ['name' => 'tha_a_idx'])
            ->addForeignKey('id_tgroup_attr', 'torrent_group_attr', 'id_torrent_group_attr', ['delete' => 'CASCADE'])
            ->create();
        $this->query("
            alter table torrent_group_attr 
                rename column id_torrent_group_attr to id_tgroup_attr
        ");
        $this->query("
            alter table torrent_group_attr rename to tgroup_attr
        ");

        // In the CI, all foreign tables including tgroup_attr and tgroup_has_attr
        // will already have been initialized by the time this migration is run.
        // In that case, ignore.
        $row = $this->fetchRow("
            select table_name
            from information_schema.tables
            where table_schema = 'relay' and table_name = 'tgroup_attr'
        ");
        if ($row === false) {
            $this->query("
                import foreign schema " . MYSQL_DB
                    . " limit to (tgroup_attr, tgroup_has_attr) from server relayer into relay;
            ");
        }
    }

    public function down(): void {
        $this->table('tgroup_has_attr')->drop()->save();
        $this->query("
            alter table tgroup_attr rename to torrent_group_attr
        ");
        $this->query("
            alter table torrent_group_attr 
                rename column id_tgroup_attr to id_torrent_group_attr
        ");
        $this->query("drop foreign table relay.tgroup_attr");
        $this->query("drop foreign table relay.tgroup_has_attr");
    }
}
