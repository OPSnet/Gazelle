<?php

use Phinx\Migration\AbstractMigration;

class PaymentCurrency extends AbstractMigration {
    public function change(): void {
        $this->table('payment_reminders')
            ->addColumn('cc', 'enum', ['values' => ['EUR', 'USD'], 'default' => 'USD'])
            ->update();
    }
}
