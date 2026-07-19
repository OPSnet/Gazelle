<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NullAuditCreator extends AbstractMigration {
    public function up(): void {
        $this->execute("
            alter table user_audit_trail alter column id_user_creator drop not null
        ");
        $this->execute("
            create table user_audit_trail_revision (
                id_user_audit_trail int not null,
                revision int not null,
                id_user int not null,
                id_user_creator int,
                created timestamptz not null,
                event varchar(20) not null,
                note text not null,
                primary key (id_user_audit_trail, revision)
            )"
        );
    }

    public function down(): void {
        $this->execute("
            update user_audit_trail set id_user_creator = 0 where id_user_creator is null
        ");
        $this->execute("
            alter table user_audit_trail alter column id_user_creator set not null
        ");
        $this->table("user_audit_trail_revision")
            ->drop()
            ->save();
    }
}
