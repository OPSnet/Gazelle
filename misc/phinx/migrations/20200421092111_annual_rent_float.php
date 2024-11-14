<?php

use Phinx\Migration\AbstractMigration;

class AnnualRentFloat extends AbstractMigration {
    public function up(): void {
        $this->execute("
            ALTER TABLE payment_reminders
                MODIFY AnnualRent float(24,12) NOT NULL DEFAULT 0,
                MODIFY `cc` enum('BTC', 'EUR','USD') NOT NULL DEFAULT 'USD'
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE payment_reminders
                MODIFY AnnualRent int(11) NOT NULL DEFAULT 0,
                MODIFY `cc` enum('EUR','USD') NOT NULL DEFAULT 'USD'
            ");
    }
}
