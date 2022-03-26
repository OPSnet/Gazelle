<?php

use Phinx\Migration\AbstractMigration;

class NullDateColumns extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE deleted_torrents MODIFY LastReseedRequest datetime DEFAULT NULL");
        $this->execute("ALTER TABLE torrents_bad_folders MODIFY TimeAdded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE forums_topic_notes MODIFY AddedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE referral_users MODIFY Joined timestamp NULL");
    }

    public function down() {
        $this->execute("ALTER TABLE deleted_torrents MODIFY LastReseedRequest datetime");
        $this->execute("ALTER TABLE torrents_bad_folders MODIFY TimeAdded datetime NOT NULL");
        $this->execute("ALTER TABLE forums_topic_notes MODIFY AddedTime datetime NOT NULL");
        $this->execute("ALTER TABLE referral_users MODIFY Joined timestamp");
    }
}
