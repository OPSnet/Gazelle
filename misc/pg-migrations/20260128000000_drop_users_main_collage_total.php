<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once __DIR__ . '/../../lib/config.php';
// phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

use Phinx\Migration\AbstractMigration;

final class DropUsersMainCollageTotal extends AbstractMigration {
    public function change(): void {
        $this->query("
            drop foreign table if exists relay.users_main
        ");
        $this->query("
            import foreign schema " . MYSQL_DB
                . " limit to (users_main) from server relayer into relay;
        ");
    }
}
