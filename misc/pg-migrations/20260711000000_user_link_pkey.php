<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserLinkPkey extends AbstractMigration {
    public function up(): void {
        $this->table('user_link')
            ->renameColumn('id', 'id_user_link')
            ->save();
        $this->query(<<<END_SQL
            select setval(
                'public.user_link_group_id_user_link_group_seq',
                (select max("ID") from relay.dupe_groups)
            )
            END_SQL
        );
    }

    public function down(): void {
        $this->table('user_link')
            ->renameColumn('id_user_link', 'id')
            ->save();
    }
}
