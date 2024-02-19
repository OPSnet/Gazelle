<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FinalTablePks extends AbstractMigration {
    public function up(): void {
        $this->execute("
            ALTER TABLE bookmarks_artists ADD PRIMARY KEY (ArtistID, UserID)
        ");
        $this->execute("
            ALTER TABLE donations_bitcoin
                ADD COLUMN donations_bitcoin_id int NOT NULL AUTO_INCREMENT,
                ADD PRIMARY KEY (donations_bitcoin_id)
        ");
        $this->execute("
            ALTER TABLE sphinx_a
                ADD COLUMN sphinx_a_id int NOT NULL AUTO_INCREMENT,
                ADD PRIMARY KEY (sphinx_a_id)
        ");
        $this->execute("
            ALTER TABLE top10_history_torrents
                ADD COLUMN top10_history_torrents_id int NOT NULL AUTO_INCREMENT,
                ADD PRIMARY KEY (top10_history_torrents_id)
        ");
        $this->execute("
            ALTER TABLE users_history_passwords
                ADD COLUMN users_history_passwords_id int NOT NULL AUTO_INCREMENT,
                ADD PRIMARY KEY (users_history_passwords_id)
        ");
        $this->execute("
            ALTER TABLE xbt_files_history
                DROP KEY xfh_uid_fid_idx,
                ADD PRIMARY KEY (uid, fid)
        ");
        $this->execute("
            ALTER TABLE xbt_snatched
                ADD COLUMN xbt_snatched_id int NOT NULL AUTO_INCREMENT,
                ADD PRIMARY KEY (xbt_snatched_id)
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE bookmarks_artists DROP PRIMARY KEY
        ");
        $this->execute("
            ALTER TABLE donations_bitcoin DROP PRIMARY KEY, DROP COLUMN donations_bitcoin_id
        ");
        $this->execute("
            ALTER TABLE sphinx_a DROP PRIMARY KEY, DROP COLUMN sphinx_a_id
        ");
        $this->execute("
            ALTER TABLE top10_history_torrents DROP PRIMARY KEY, DROP COLUMN top10_history_torrents_id
        ");
        $this->execute("
            ALTER TABLE users_history_passwords DROP PRIMARY KEY, DROP COLUMN users_history_passwords_id
        ");
        $this->execute("
            ALTER TABLE xbt_files_history DROP PRIMARY KEY, ADD UNIQUE KEY xfh_uid_fid_idx (uid, fid)
        ");
        $this->execute("
            ALTER TABLE xbt_snatched DROP PRIMARY KEY, DROP COLUMN xbt_snatched_id
        ");
    }
}
