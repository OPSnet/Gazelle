<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

final class ReindexUsersComments extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE users_comments_last_read
                DROP PRIMARY KEY,
                DROP KEY /* IF EXISTS */ Page,
                ADD PRIMARY KEY (Page, PageID, UserID),
                ADD KEY uclr_user_idx (UserID)
        ");
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("
            ALTER TABLE users_comments_last_read
                DROP PRIMARY KEY,
                DROP KEY /* IF EXISTS */ uclr_user_idx,
                ADD PRIMARY KEY (UserID, Page, PageID),
                ADD KEY Page (Page, PageID)
        ");
    }
}
