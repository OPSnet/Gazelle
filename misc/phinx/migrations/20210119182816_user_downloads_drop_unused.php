<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

final class UserDownloadsDropUnused extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE users_downloads DROP KEY /* IF EXISTS */ UserID
        ");
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE users_downloads ADD KEY /* IF NOT EXISTS */ UserID (UserID)
        ");
    }
}
