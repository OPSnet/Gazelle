<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* After this has landed in production, run the following queries */
/*
insert into history_bonus
select "ID", "ItemID", "UserID", "Price", "OtherUserID", "PurchaseDate"
from relay.bonus_history
on conflict (id_history_bonus) do nothing;

update history_bonus set id_user_other = null where id_user_other = 0;
*/

final class BonusHistory extends AbstractMigration {
    public function change(): void {
        $this->table('history_bonus', ['id' => false, 'primary_key' => 'id_history_bonus'])
            ->addColumn('id_history_bonus', 'integer', ['identity' => true])
            ->addColumn('id_bonus_item',    'integer')
            ->addColumn('id_user',          'integer')
            ->addColumn('price',            'integer')
            ->addColumn('id_user_other',    'integer', ['null' => true])
            ->addColumn('created',          'timestamp',
                ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['id_bonus_item'], ['name' => 'hb_bi_idx'])
            ->addIndex(['id_user'], ['name' => 'hb_u_idx'])
            ->addIndex(['id_user_other', 'id_user'], ['name' => 'hb_uo_uu_idx'])
            ->addForeignKey('id_bonus_item', 'bonus_item', 'id_bonus_item')
            ->save();
    }
}
