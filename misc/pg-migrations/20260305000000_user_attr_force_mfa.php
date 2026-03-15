<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserAttrForceMfa extends AbstractMigration {
    public function up(): void {
        $this->query(<<<END_SQL
            insert into user_attr (id_user_attr, name, description)
            select "ID", "Name", "Description"
            from relay.user_attr
            where "Name" = 'enforce-mfa'
            on conflict (name) do nothing
        END_SQL
        );
    }

    public function down(): void {
        $this->query("
            delete from user_attr where name = 'enforce-mfa'
        ");
    }
}
