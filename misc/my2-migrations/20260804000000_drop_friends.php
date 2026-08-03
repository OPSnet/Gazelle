<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropFriends extends AbstractMigration {
    public function up(): void {
        $this->table('friends')->drop()->save();
    }

    public function down(): void {
        $this->query('
            CREATE TABLE friends (
                UserID int NOT NULL,
                FriendID int NOT NULL,
                Comment mediumtext NOT NULL,
                PRIMARY KEY (UserID, FriendID),
                KEY (FriendID)
            )
        ');
    }
}
