<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropBonusHistory extends AbstractMigration {
    public function up(): void {
        $this->table('bonus_history')->drop()->save();
    }

    public function down(): void {
        $this->query('
            CREATE TABLE bonus_history (
              ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              ItemID int NOT NULL,
              UserID int NOT NULL,
              Price int NOT NULL,
              OtherUserID int,
              PurchaseDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY ItemID (ItemID),
              KEY bh_ou_u_idx (OtherUserID, UserID),
              KEY UserID (UserID),
              FOREIGN KEY (UserID) REFERENCES users_main (ID) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }
}
