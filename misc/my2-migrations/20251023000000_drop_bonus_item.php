<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropBonusItem extends AbstractMigration {
    public function up(): void {
        $this->table('bonus_history')->dropForeignKey('ItemID')->save();
        $this->table('bonus_item')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE bonus_item (
                ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                Price int NOT NULL,
                Amount int DEFAULT NULL,
                MinClass int NOT NULL DEFAULT '0',
                FreeClass int NOT NULL DEFAULT '999999',
                Label varchar(32) NOT NULL,
                Title varchar(64) NOT NULL,
                sequence int NOT NULL DEFAULT '0',
                UNIQUE KEY Label (Label)
            )
        ");
        // The foreign key cannot be rolled back because bonus_item is now empty,
        // and bonus_history contain references to ID values that are now only
        // present in the Pg table. As such, the following call will fail.
        //
        // If bonus_item is reloaded from the Pg table, it will succeed. To roll
        // back (in order to roll back an earlier migration), this can be
        // commented out. But when the migrations are rolled forward again, the
        // dropForeignKey() call will fail. So that would have to be commented
        // out as well.
        $this->table('bonus_history')
            ->addForeignKey('ItemID', 'bonus_item', 'ID', ['constraint' => 'bonus_history_fk_item'])
            ->save();
    }
}
