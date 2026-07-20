<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistUsageRefresh extends AbstractMigration {
    public function change(): void {
        $MYSQLDB = MYSQL_DB;
        $this->query('drop foreign table if exists relay.artist_usage');
        $this->query("
            import foreign schema $MYSQLDB limit to (artist_usage) from server relayer into relay
        ");
    }
}
