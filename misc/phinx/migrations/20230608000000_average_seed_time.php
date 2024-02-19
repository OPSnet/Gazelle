<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AverageSeedTime extends AbstractMigration {
    public function change(): void {
        $this->table('user_summary', ['id' => false, 'primary_key' => 'user_id'])
             ->addColumn('seedtime_hour', 'biginteger', ['default' => 0])
             ->save();
    }
}
