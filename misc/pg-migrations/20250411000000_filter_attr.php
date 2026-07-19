<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FilterAttr extends AbstractMigration {
    public function up(): void {
        $this->query("
            insert into user_attr (id_user_attr, name, description)
            select \"ID\", \"Name\", \"Description\"
            from relay.user_attr
            where \"Name\" = 'show-all-tags'
            on conflict (name)
            do nothing
        ");
    }

    public function down(): void {
        $this->query("
            delete from user_attr where name = 'show-all-tags';
        ");
    }
}
