<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuditTrailHidden extends AbstractMigration {
    public function up(): void {
        $this->query("
            insert into user_attr (id_user_attr, name, description)
            select \"ID\", \"Name\", \"Description\"
            from relay.user_attr
            where \"Name\" = 'audit-trail-hidden'
        ");
    }

    public function down(): void {
        $this->query("
            delete from user_attr where name = 'audit-trail-hidden';
        ");
    }
}
