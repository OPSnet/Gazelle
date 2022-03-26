<?php

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

class XfuByUid extends AbstractMigration {
    public function up() {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("ALTER TABLE xbt_files_users
            ADD KEY /* IF NOT EXISTS */ xfu_uid_idx (uid)
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE xbt_files_users
            DROP KEY /* IF EXISTS */ xfu_uid_idx
        ");
    }
}
