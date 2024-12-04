<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveMfa extends AbstractMigration {
    public function up(): void {
        if ($this->fetchRow('select count(*) from users_main where 2FA_Key != ""')[0] > 0) { // @phpstan-ignore-line
            throw new RuntimeException('MFA keys not migrated yet. execute bin/migrate-mfa first.');
        }
        $this->table('users_main')
            ->removeColumn('2FA_Key')
            ->removeColumn('Recovery')
            ->save();
    }

    public function down(): void {
        $this->table('users_main')
            ->addColumn('2FA_Key', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 16,
            ])
            ->addColumn('Recovery', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
            ])
            ->save();
    }
}
