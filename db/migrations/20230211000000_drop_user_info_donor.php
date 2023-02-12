<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserInfoDonor extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')->removeColumn('Donor')->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('Donor', 'enum', [
                'null'    => false,
                'default' => '0',
                'values' => ['0', '1'],
            ])
            ->save();
    }
}
