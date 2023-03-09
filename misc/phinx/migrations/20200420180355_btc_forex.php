<?php

use Phinx\Migration\AbstractMigration;

class BtcForex extends AbstractMigration {
    public function up(): void {
        $this->table('btc_forex', ['id' => false,   'primary_key' => 'btc_forex_id'])
            ->addColumn('btc_forex_id',  'integer',  ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('cc',            'enum',     ['default' => 'USD', 'values' => ['EUR', 'USD']])
            ->addColumn('rate',          'float',    ['precision' => 24, 'scale' => 12])
            ->addColumn('forex_date',    'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('btc_forex')->drop()->update();
    }
}
