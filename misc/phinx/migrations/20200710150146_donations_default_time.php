<?php

use Phinx\Migration\AbstractMigration;

class DonationsDefaultTime extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE donations
            MODIFY COLUMN Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        // To avoid 0000-00-00 if the mysql config allows zero dates
        $this->execute("UPDATE users_donor_ranks SET DonationTime = now() WHERE DonationTime IS NULL");
        $this->execute("UPDATE users_donor_ranks SET RankExpirationTime = now() WHERE RankExpirationTime IS NULL");
        $this->execute("ALTER TABLE users_donor_ranks
            MODIFY COLUMN DonationTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY COLUMN RankExpirationTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE donations
            MODIFY COLUMN Time datetime NOT NULL
        ");
        // cannot rollback NULL dates that were unnullified, tough cookies
        $this->execute("ALTER TABLE users_donor_ranks
            MODIFY COLUMN DonationTime datetime DEFAULT NULL,
            MODIFY COLUMN RankExpirationTime datetime DEFAULT NULL
        ");
    }
}

