<?php

use Phinx\Migration\AbstractMigration;

class PmOnDelete extends AbstractMigration {
    public function change(): void {
        // boolean => tinyint(1)
        $this->table('users_info')
            ->addColumn('NotifyOnDeleteSeeding', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('NotifyOnDeleteSnatched', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->addColumn('NotifyOnDeleteDownloaded', 'enum', ['values' => ['0', '1'], 'default' => '1'])
            ->update();
    }
}
