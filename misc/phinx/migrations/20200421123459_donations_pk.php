<?php

use Phinx\Migration\AbstractMigration;

class DonationsPk extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE donations
                ADD COLUMN btc decimal(24,12) NULL,
                ADD COLUMN donations_id int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ADD KEY don_time_idx (Time),
                ADD KEY don_userid_time_idx (UserID, Time)
        ');
    }

    public function down(): void {
        $this->execute('
            ALTER TABLE donations
                DROP COLUMN btc,
                DROP PRIMARY KEY,
                DROP COLUMN donations_id,
                DROP KEY don_time_idx,
                DROP KEY don_userid_time_idx
        ');
    }
}
