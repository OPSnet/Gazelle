<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserclassLevel extends AbstractMigration{
    public function up(): void {
        $this->table('userclass')
            ->addColumn('level', 'integer', ['default' => 0])
            ->save();
        $this->query(<<<END_SQL
            insert into userclass (id_userclass, name, level, is_staff)
            select "ID",
                "Name",
                "Level",
                case when "Level" >= 800 then true else false end
            from relay.permissions pmy
            END_SQL
        );

        $this->query("
            insert into privilege (id_privilege_group, seq, name, description)
            values (
                (select id_privilege_group from privilege_group where description = 'Privileges for user administration'),
                2070,
                'buffer-edit',
                'Can adjust upload/download values'
            )
        ");
    }

    public function down(): void {
        $this->table('userclass')
            ->removeColumn('level')
            ->save();
        $this->query("
            delete from privilege where name = 'buffer-edit'
        ");
    }
}
