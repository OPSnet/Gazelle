<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserAuditTrail extends AbstractMigration {
    public function up(): void {
        $this->execute("
            create table user_audit_trail (
                id_user_audit_trail int primary key generated always as identity,
                id_user int not null,
                id_user_creator int not null,
                created timestamptz not null default current_timestamp,
                event varchar(20) not null,
                note text not null,
                note_ts tsvector generated always as (to_tsvector('simple', note)) stored
            )"
        );
        $this->execute("comment on column user_audit_trail.id_user_creator is 'system events have value 0'");
        $this->execute('create index uat_u_evt_idx ON user_audit_trail (id_user, event)');
        $this->execute('create index uat_u_c_idx ON user_audit_trail (id_user, created)');
        $this->execute('create index uat_ts_note_idx ON user_audit_trail USING gin (note_ts)');
    }

    public function down(): void {
        $this->table('user_audit_trail')->drop()->save();
    }
}
