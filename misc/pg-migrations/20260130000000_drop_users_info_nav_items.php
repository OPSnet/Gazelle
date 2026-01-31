<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once __DIR__ . '/../../lib/config.php';
// phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

final class DropUsersInfoNavItems extends AbstractMigration {
    public function change(): void {
        $this->query("
            drop foreign table if exists relay.users_info
        ");
        $this->query("
            import foreign schema " . MYSQL_DB
                . " limit to (users_info) from server relayer into relay;
        ");
    }
}
