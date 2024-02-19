<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

final class IndexXbtFilesUsers extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE xbt_files_users
                DROP KEY /* IF EXISTS */ xfu_uid_idx,
                DROP KEY /* IF EXISTS */ fid_idx,
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (fid, uid, peer_id)
        ");
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE xbt_files_users
                ADD KEY xfu_uid_idx (uid),
                ADD KEY fid_idx (fid),
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (peer_id, fid, uid),
        ");
    }
}
