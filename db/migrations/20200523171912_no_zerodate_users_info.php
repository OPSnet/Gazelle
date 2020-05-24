<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersInfo extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE users_info
            MODIFY Warned datetime DEFAULT NULL,
            MODIFY ResetExpires datetime DEFAULT NULL,
            MODIFY JoinDate datetime DEFAULT CURRENT_TIMESTAMP,
            MODIFY RatioWatchEnds datetime DEFAULT NULL,
            MODIFY BanDate datetime DEFAULT NULL
        ");
        $this->execute("UPDATE users_info SET Warned = NULL WHERE Warned = '0000-00-00 00:00:00'");
        $this->execute("UPDATE users_info SET ResetExpires = NULL WHERE ResetExpires = '0000-00-00 00:00:00'");
        $this->execute("UPDATE users_info SET JoinDate = now() WHERE JoinDate = '0000-00-00 00:00:00'");
        $this->execute("UPDATE users_info SET RatioWatchEnds = NULL WHERE RatioWatchEnds = '0000-00-00 00:00:00'");
        $this->execute("UPDATE users_info SET BanDate = NULL WHERE BanDate = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE users_info
            MODIFY Warned timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY ResetExpires datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY JoinDate datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY RatioWatchEnds datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY BanDate datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
