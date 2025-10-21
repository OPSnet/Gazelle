<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserLink extends AbstractMigration {
    public function up(): void {
        $this->table('user_link_group', ['id' => false, 'primary_key' => 'id_user_link_group'])
            ->addColumn('id_user_link_group', 'integer', ['identity' => true])
            ->addColumn('comment', 'text', ['null' => true])
            ->save();
        $this->table('user_link', [])
            ->addColumn('id_user_link_group', 'integer')
            ->addColumn('id_user',            'integer')
            ->addColumn('id_user_creator',    'integer')
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('id_user_link_group', 'user_link_group', 'id_user_link_group', ['delete' => 'CASCADE'])
            ->save();

        $this->query(<<<END_SQL
            insert into user_link_group (
                id_user_link_group,
                comment
            )
            select dg."ID", dg."Comments"
            from relay.dupe_groups dg
            where exists (
                select 1
                from relay.users_dupes ud
                where ud."GroupID" = dg."ID"
            )
            END_SQL
        );
        $this->query(<<<END_SQL
            insert into user_link (
                id_user_link_group,
                id_user,
                id_user_creator
            )
            select "GroupID", "UserID",
                (
                    select um."ID"
                    from relay.users_main um
                    inner join relay.permissions p on (p."ID" = um."PermissionID")
                    where um."Enabled" = '1'
                    order by p."Level" DESC
                    LIMIT 1
                )
            from relay.users_dupes
            END_SQL
        );
    }

    public function down(): void {
        $this->table('user_link')->drop()->save();
        $this->table('user_link_group')->drop()->save();
    }
}
