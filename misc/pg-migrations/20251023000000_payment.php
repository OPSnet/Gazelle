<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class Payment extends AbstractMigration {
    public function up(): void {
        $this->query("create type currency_t as enum ('EUR', 'USD', 'XBT')");
        $this->table('payment', ['id' => false, 'primary_key' => 'id_payment'])
            ->addColumn('id_payment', 'integer', ['identity' => true])
            ->addColumn('expiry', 'date', ['default' => 'now('])
            ->addColumn('annual_rent', 'float')
            ->addColumn('currency', Literal::from('currency_t'), ['default' => 'USD'])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('name', 'string', ['length' => 100])
            ->save();

        $this->query(<<<END_SQL
            insert into payment (
                id_payment,
                expiry,
                annual_rent,
                currency,
                active,
                name
            )
            select "ID",
                "Expiry",
                "AnnualRent",
                case
                    when cc = 'USD' then 'USD'::currency_t
                    when cc = 'EUR' then 'EUR'::currency_t
                    else 'XBT'::currency_t
                end,
                case when "Active" = 0 then false else true end,
                "Text"
            from relay.payment_reminders
            END_SQL
        );
    }

    public function down(): void {
        $this->table('payment')->drop()->save();
    }
}
