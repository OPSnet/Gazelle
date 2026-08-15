<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeletedNullable extends AbstractMigration {
    public function up(): void {
        $this->table('deleted_torrents')
            ->changeColumn('Media', 'string', ['length' => 20, 'null' => false])
            ->changeColumn('Time', 'datetime', ['null' => false])
            ->changeColumn('UserID', 'integer', ['null' => false])
            ->save();
        $this->query("
            alter table deleted_torrents_group
                modify WikiBody mediumtext not null,
                modify WikiImage varchar(255) default null;
        ");
    }

    public function down(): void {
        $this->table('deleted_torrents')
            ->changeColumn('Media', 'string', ['length' => 20, 'null' => true])
            ->changeColumn('Time', 'datetime', ['null' => true])
            ->changeColumn('UserID', 'integer', ['null' => true])
            ->save();
        $this->query("
            alter table deleted_torrents_group
                modify WikiBody mediumtext default null,
                modify WikiImage varchar(255) not null;
        ");
    }
}
