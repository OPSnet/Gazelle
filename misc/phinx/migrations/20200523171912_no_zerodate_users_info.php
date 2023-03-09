<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersInfo extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE users_info
            MODIFY Warned datetime DEFAULT NULL,
            MODIFY ResetExpires datetime DEFAULT NULL,
            MODIFY JoinDate datetime DEFAULT CURRENT_TIMESTAMP,
            MODIFY RatioWatchEnds datetime DEFAULT NULL,
            MODIFY BanDate datetime DEFAULT NULL
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE users_info
            MODIFY Warned timestamp NOT NULL,
            MODIFY ResetExpires datetime NOT NULL,
            MODIFY JoinDate datetime NOT NULL,
            MODIFY RatioWatchEnds datetime NOT NULL,
            MODIFY BanDate datetime
        ");
    }
}
