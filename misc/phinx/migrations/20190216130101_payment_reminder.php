<?php

use Phinx\Migration\AbstractMigration;

class PaymentReminder extends AbstractMigration {
    public function change(): void {
        $this->table('payment_reminders', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
             ->addColumn('Text', 'string', ['limit' => 100])
             ->addColumn('Expiry', 'timestamp', ['null' => true])
             ->addColumn('Active', 'boolean', ['default' => true])
             ->create();
    }
}
