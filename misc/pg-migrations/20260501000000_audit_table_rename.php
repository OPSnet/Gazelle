<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTableRename extends AbstractMigration {
    public function up(): void {
        // thank god for Pg DDL transactions
        $this->query("
            drop view audit_trail_user
        ");
        $this->query("
            alter table user_audit_trail
                rename column id_user_audit_trail to id_audit_trail_user
        ");
        $this->query("
            alter table user_audit_trail rename to audit_trail_user
        ");

        $this->query("
            drop view audit_trail_user_revision
        ");
        $this->query("
            alter table user_audit_trail_revision
                rename column id_user_audit_trail to id_audit_trail_user
        ");
        $this->query("
            alter table user_audit_trail_revision rename to audit_trail_user_revision
        ");

        $this->query("
            drop view audit_trail_site
        ");
        $this->query("
            alter table site_log
                rename column id_site_log to id_audit_trail_site
        ");
        $this->query("
            alter table site_log rename to audit_trail_site
        ");
        $this->query("
            update table_row_count set table_name = 'audit_trail_site' where table_name = 'site_log'
        ");
        $this->query("
            alter trigger track_row_count_site_log on audit_trail_site
                rename to track_row_count_audit_trail_site
        ");
    }

    public function down(): void {
        $this->query("
            alter trigger track_row_count_audit_trail_site on audit_trail_site
                rename to track_row_count_site_log
        ");
        $this->query("
            update table_row_count set table_name = 'site_log' where table_name = 'audit_trail_site'
        ");
        $this->query("
            alter table audit_trail_site
                rename column id_audit_trail_site to id_site_log
        ");
        $this->query("
            alter table audit_trail_site rename to site_log
        ");

        $this->query("
            alter table audit_trail_user
                rename column id_audit_trail_user to id_user_audit_trail
        ");
        $this->query("
            alter table audit_trail_user rename to user_audit_trail
        ");
        $this->query("
            alter table audit_trail_user_revision
                rename column id_audit_trail_user to id_user_audit_trail
        ");
        $this->query("
            alter table audit_trail_user_revision rename to user_audit_trail_revision
        ");

        $this->query("
            create view audit_trail_site (
                id_audit_trail_site,
                created,
                note,
                note_ts
            ) as
            select
                id_site_log,
                created,
                note,
                note_ts
            from site_log
        ");
        $this->query("
            create view audit_trail_user (
                id_audit_trail_user,
                id_user,
                id_user_creator,
                created,
                event,
                note,
                note_ts
            ) as
            select
                id_user_audit_trail,
                id_user,
                id_user_creator,
                created,
                event,
                note,
                note_ts
            from user_audit_trail
        ");
        $this->query("
            create view audit_trail_user_revision (
                id_audit_trail_user,
                revision,
                id_user,
                id_user_creator,
                created,
                event,
                note
            ) as
            select
                id_user_audit_trail,
                revision,
                id_user,
                id_user_creator,
                created,
                event,
                note
            from user_audit_trail_revision
        ");
    }
}
