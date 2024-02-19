<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DbUpdatedModified extends AbstractMigration {
    public function up(): void {
        $this->table('requests')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->table('torrents')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->table('deleted_torrents')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->table('torrents_group')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->table('deleted_torrents_group')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();
        $this->table('users_main')
             ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->save();

        $this->execute("update requests set created = TimeAdded, updated = TimeAdded");
        $this->execute("update torrents set created = Time, updated = Time");
        $this->execute("create temporary table tgmin (ID integer not null primary key, created datetime not null, recent datetime not null)");
        $this->execute("insert into tgmin select t.GroupID, min(t.Time), max(t.Time) from torrents t GROUP BY t.GroupID");
        $this->execute("
            UPDATE torrents_group tg
            INNER JOIN tgmin USING (ID)
            SET tg.created = tgmin.created,
                tg.updated = tgmin.recent
        ");
        $this->execute("
            UPDATE users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            SET um.created = ui.JoinDate,
                um.updated = ui.JoinDate
        ");
    }

    public function down(): void {
        $this->table('requests')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
        $this->table('torrents')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
        $this->table('deleted_torrents')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
        $this->table('torrents_group')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
        $this->table('deleted_torrents_group')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
        $this->table('users_main')
             ->removeColumn('created')
             ->removeColumn('updated')
             ->save();
    }
}
