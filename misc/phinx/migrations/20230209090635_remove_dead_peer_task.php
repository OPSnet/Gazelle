<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveDeadPeerTask extends AbstractMigration {
    public function up(): void {
        $this->query("DELETE FROM periodic_task WHERE classname = 'RemoveDeadPeers'");
    }

    public function down(): void {
        $this->table('periodic_task')
            ->insert([[
                'name'        => 'Remove Dead Peers',
                'classname'   => 'RemoveDeadPeers',
                'description' => 'Removes dead peers',
                'period'      => 3600,
            ]])
            ->save();
    }
}
