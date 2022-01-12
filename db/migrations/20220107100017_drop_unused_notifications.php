<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DropUnusedNotifications extends AbstractMigration
{
    public function up(): void {
        $this->table('users_notifications_settings')
            ->removeColumn('SiteAlerts')
            ->removeColumn('ForumAlerts')
            ->removeColumn('CollageAlerts')
            ->removeColumn('RequestAlerts')
            ->removeColumn('TorrentAlerts')
            ->save();
    }

    public function down(): void {
        $this->table('users_notifications_settings')
            ->addColumn('ForumAlerts',   'boolean', ['null' => true, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('CollageAlerts', 'boolean', ['null' => true, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('SiteAlerts',    'boolean', ['null' => true, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('RequestAlerts', 'boolean', ['null' => true, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('TorrentAlerts', 'boolean', ['null' => true, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->save();
    }
}
