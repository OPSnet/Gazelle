<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/*
insert into user_has_friend (id_user, id_user_friend, comment)
select "UserID", "FriendID", "Comment" from relay.friends
*/

final class PgFriend extends AbstractMigration {
    public function up(): void {
        $this->table('user_has_friend', ['id' => false, 'primary_key' => ['id_user', 'id_user_friend']])
            ->addColumn('id_user', 'integer', ['identity' => true])
            ->addColumn('id_user_friend', 'integer')
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('id_user_friend', ['name' => 'uf_f_idx'])
            ->save();
        $this->query('
            create trigger user_has_friend_modified
            before update on user_has_friend
            for each row
            execute function modified_now()
        ');
    }

    public function down(): void {
        $this->table('user_has_friend')->drop()->save();
    }
}
