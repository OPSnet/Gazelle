<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropCoverArt extends AbstractMigration {
    public function up(): void {
        $this->table('cover_art')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE cover_art (
                ID int NOT NULL PRIMARY KEY AUTO_INCREMENT,
                GroupID int NOT NULL,
                Image varchar(255) NOT NULL,
                Summary varchar(100) NOT NULL,
                UserID int NOT NULL,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY GroupID (GroupID,Image)
            )
        ");
    }
}
