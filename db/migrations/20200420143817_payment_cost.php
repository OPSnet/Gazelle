<?php

use Phinx\Migration\AbstractMigration;

class PaymentCost extends AbstractMigration {
    public function up() {
        $this->execute("
            ALTER TABLE payment_reminders
                MODIFY Expiry date NOT NULL DEFAULT (current_date),
                ADD COLUMN AnnualRent integer NOT NULL DEFAULT 0
        ");
    }

    public function down() {
        $this->execute("
            ALTER TABLE payment_reminders MODIFY Expiry datetime, DROP COLUMN AnnualRent
        ");
    }
}
