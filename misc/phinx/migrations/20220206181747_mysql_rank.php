<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MysqlRank extends AbstractMigration
{
    public function change(): void {
        $this->table('donations')->renameColumn('Rank', 'donor_rank')->save();
        $this->table('users_donor_ranks')->renameColumn('Rank', 'donor_rank')->save();
        $this->table('top10_history_torrents')->renameColumn('Rank', 'sequence')->save();
    }
}
