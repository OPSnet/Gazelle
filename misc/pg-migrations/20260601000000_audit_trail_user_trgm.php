<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTrailUserTrgm extends AbstractMigration {
    public function up(): void {
        $this->query('
            create index atu_note_trgm_id on audit_trail_user using gin (note gin_trgm_ops)
        ');
    }

    public function down(): void {
        $this->query('
            drop index atu_note_trgm_id;
        ');
    }
}
