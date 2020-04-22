<?php

use Phinx\Migration\AbstractMigration;

class BitcoinIsXbt extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE payment_reminders MODIFY cc enum('BTC', 'EUR', 'USD', 'XBT') NOT NULL DEFAULT 'USD'");
        $this->execute("UPDATE payment_reminders SET cc = 'XBT' WHERE cc = 'BTC'");
        $this->execute("ALTER TABLE payment_reminders MODIFY cc enum('XBT', 'EUR', 'USD') NOT NULL DEFAULT 'USD'");
    }
    public function down() {
        $this->execute("ALTER TABLE payment_reminders MODIFY cc enum('BTC', 'EUR', 'USD', 'XBT') NOT NULL DEFAULT 'USD'");
        $this->execute("UPDATE payment_reminders SET cc = 'BTC' WHERE cc = 'XBT'");
        $this->execute("ALTER TABLE payment_reminders MODIFY cc enum('BTC', 'EUR', 'USD') NOT NULL DEFAULT 'USD'");
    }
}
