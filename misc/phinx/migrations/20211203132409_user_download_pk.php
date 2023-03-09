<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDownloadPk extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE users_downloads
                DROP PRIMARY KEY,
                ADD COLUMN users_downloads_id integer(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ADD KEY user_idx (UserID)
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE users_downloads
                DROP PRIMARY KEY,
                DROP KEY user_idx,
                DROP COLUMN users_downloads_id,
                ADD PRIMARY KEY (UserID, TorrentID, Time)
        ");
    }
}
