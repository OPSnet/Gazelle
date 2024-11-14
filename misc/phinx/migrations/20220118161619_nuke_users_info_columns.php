<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NukeUsersInfoColumns extends AbstractMigration
{
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('BitcoinAddress')
            ->removeColumn('HideCountryChanges')
            ->removeColumn('NotifyOnQuote')
            ->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('BitcoinAddress', 'string', ['limit' => 34])
            ->addColumn('HideCountryChanges', 'enum', ['values' => ['0', '1'], 'default' => '0'])
            ->addColumn('NotifyOnQuote', 'enum', [ 'values' => ['0', '1', '2'], 'default' => '0'])
            ->save();
    }
}
