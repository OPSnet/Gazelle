<?php

use Phinx\Migration\AbstractMigration;

class MaxCollages extends AbstractMigration
{
    public function up(): void {
        $this->table('users_info')
             ->addColumn('collages', 'integer', [
                 'limit'   => 6,
                 'signed'  => false,
                 'default' => 0,
             ])
             ->save();

        $this->execute("
            UPDATE users_info ui
            INNER JOIN
            (
                SELECT UserID, count(*) AS collages
                FROM collages
                WHERE CategoryID = 0
                GROUP BY UserID
            ) c USING (UserID)
            SET ui.collages = c.collages");

        $this->table('bonus_item')
             ->addColumn('sequence', 'integer', [
                 'limit'   => 6,
                 'signed'  => false,
                 'default' => 0,
             ])
             ->insert([
                 [
                     'Price'    => 2500,
                     'Amount'   => 1,
                     'MinClass' => 150,
                     'Label'    => 'collage-1',
                     'Title'    => 'Buy a Personal Collage Slot',
                 ],
             ])
             ->save();

        $this->execute("
            UPDATE bonus_item bi
            INNER JOIN
            (
                SELECT ID, row_number() OVER (ORDER BY FIELD(label, 'token-1', 'token-4', 'token-2', 'token-3', 'other-1', 'other-4', 'other-2', 'other-3', 'title-bb-n', 'title-bb-y', 'title-off', 'invite', 'collage-1')) AS sequence
                FROM bonus_item
            ) s ON (s.ID = bi.ID)
            SET bi.sequence = s.sequence");
    }

    public function down(): void {
        $this->table('users_info')
             ->removeColumn('collages')
             ->save();

        $this->table('bonus_item')
             ->removeColumn('sequence')
             ->save();

        $this->execute("DELETE FROM bonus_item WHERE label = 'collage-1'");
    }
}
