<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TimeIndexStats extends AbstractMigration {
    public function change(): void {
        foreach (['users_stats_daily', 'users_stats_monthly', 'users_stats_yearly'] as $t) {
            $this->table($t)->addIndex(['Time'], [ 'name' => 'usd_time_idx', ])->update();
        }
    }
}
