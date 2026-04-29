<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTableWritableView extends AbstractMigration {
    public function up(): void {
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

    public function down(): void {
        $this->query("
            drop view audit_trail_user_revision
        ");
        $this->query("
            drop view audit_trail_user
        ");
        $this->query("
            drop view audit_trail_site
        ");
    }
}
