<?php

use Phinx\Migration\AbstractMigration;

class PaymentCost extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE payment_reminders MODIFY Expiry date NOT NULL DEFAULT current_date, ADD COLUMN AnnualRent integer NOT NULL DEFAULT 0");
        $this->execute("UPDATE payment_reminders SET Expiry = NULL WHERE Expiry = '0000-00-00'");
    }

    public function down() {
        $this->execute("ALTER TABLE payment_reminders MODIFY Expiry datetime default '0000-00-00 00:00:00, DROP COLUMN AnnualRent");
        $this->execute("UPDATE payment_reminders SET Expiry = '0000-00-00 00:00:00' WHERE Expiry is NULL");
    }
}
