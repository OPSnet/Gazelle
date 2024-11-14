<?php

use Phinx\Migration\AbstractMigration;

class DonationsXbt extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE donations CHANGE btc xbt decimal(24,12) NULL");
        $this->execute("RENAME TABLE btc_forex TO xbt_forex");
    }

    public function down(): void {
        $this->execute("ALTER TABLE donations CHANGE xbt btc decimal(24,12) NULL");
        $this->execute("RENAME TABLE xbt_forex TO btc_forex");
    }
}
