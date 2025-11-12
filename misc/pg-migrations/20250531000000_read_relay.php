<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once __DIR__ . '/../../lib/config.php';
// phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

final class ReadRelay extends AbstractMigration {
    public function up(): void {
        $login  = MYSQL_RO_USER;
        $pass   = MYSQL_RO_PASS;
        $pgUser = PG_RO_USER;

        $this->query("
            create user mapping for $pgUser
            server relayer
            options (
                username '$login',
                password '$pass'
            )
        ");
        $this->query("
            grant usage on schema relay to $pgUser
        ");
        $this->query("
            grant select on all tables in schema relay to $pgUser
        ");
        $this->query("
            alter default privileges for role " . PG_RW_USER . " in schema relay grant select on tables to $pgUser;        
        ");
    }

    public function down(): void {
        $pgUser = PG_RO_USER;
        $this->query("
            drop user mapping for $pgUser server relayer
        ");
    }
}
