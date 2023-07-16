<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReseedRequestTask extends AbstractMigration
{
    public function up(): void {
        $this->table('periodic_task')
             ->insert([
                'name'        => 'Reset Reseed Request',
                'classname'   => 'ResetReseedRequest',
                'description' => 'Process torrents that can be requested again for reseed',
                'period'      => 60 * 60,
                'is_enabled'  => 1,
            ])
            ->save();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(['classname' => 'ReseedRequest'])
            ->execute();
    }
}
