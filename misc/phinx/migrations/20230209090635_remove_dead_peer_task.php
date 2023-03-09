<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveDeadPeerTask extends AbstractMigration {
    public function up(): void {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(['classname' => 'RemoveDeadPeers'])
            ->execute();
    }
    public function down(): void {
        $this->table('periodic_task')
            ->insert([[
                'name' => 'Remove Dead Peers',
                'classname' => 'RemoveDeadPeers',
                'description' => 'Removes dead peers',
                'period' => 60 * 60
            ]])
            ->save();
    }
}
