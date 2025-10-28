<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropPayment extends AbstractMigration {
    public function up(): void {
        $this->table('payment_reminders')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE payment_reminders (
                ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                Text varchar(100) NOT NULL,
                Expiry date NOT NULL DEFAULT (curdate()),
                Active tinyint(1) NOT NULL DEFAULT '1',
                AnnualRent float(24,12) NOT NULL DEFAULT '0.0',
                cc enum('XBT','EUR','USD') NOT NULL DEFAULT 'USD'
            )
        ");
    }
}
