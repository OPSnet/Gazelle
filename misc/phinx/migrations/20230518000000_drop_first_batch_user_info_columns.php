<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropFirstBatchUserInfoColumns extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('Avatar')
            ->removeColumn('AuthKey')
            ->removeColumn('collages')
            ->removeColumn('DownloadAlt')
            ->removeColumn('Info')
            ->removeColumn('InfoTitle')
            ->removeColumn('Inviter')
            ->removeColumn('NotifyOnDeleteSeeding')
            ->removeColumn('NotifyOnDeleteSnatched')
            ->removeColumn('NotifyOnDeleteDownloaded')
            ->removeColumn('ResetExpires')
            ->removeColumn('ResetKey')
            ->removeColumn('ShowTags')
            ->removeColumn('StyleID')
            ->removeColumn('StyleURL')
            ->removeColumn('SupportFor')
            ->removeColumn('Warned')
            ->removeColumn('WarnedTimes')
            ->save();

        $this->execute("
            DELETE FROM periodic_task_history
            WHERE periodic_task_id = (
                SELECT periodic_task_id FROM periodic_task WHERE classname = 'RemoveExpiredWarnings'
            )
        ");
        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'RemoveExpiredWarnings'
        ");
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('Avatar', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('AuthKey', 'string', ['limit' => 32])
            ->addColumn('collages', 'integer', ['default' => 0])
            ->addColumn('DownloadAlt', 'enum', ['values' => ['0', '1'], 'default' => '0'])
            ->addColumn('Info', 'text', ['limit' => 65535])
            ->addColumn('InfoTitle', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('Inviter', 'integer', ['default' => '0'])
            ->addColumn('NotifyOnDeleteSeeding', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('NotifyOnDeleteSnatched', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('NotifyOnDeleteDownloaded', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('ResetKey', 'string', ['limit' => 32])
            ->addColumn('ResetExpires', 'datetime', ['null' => true])
            ->addColumn('ShowTags', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('StyleID', 'integer')
            ->addColumn('StyleURL', 'string', ['null' => true, 'limit' => 255])
            ->addColumn('SupportFor', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('Warned', 'datetime', ['null' => true])
            ->addColumn('WarnedTimes', 'integer', ['default' => '0'])
            ->save();

        $this->table('periodic_task')
             ->insert([[
                'name'        => 'Remove Expired Warnings',
                'classname'   => 'RemoveExpiredWarnings',
                'description' => 'Removes expired warnings',
                'period' => 60 * 60
            ]])
            ->save();
    }
}
