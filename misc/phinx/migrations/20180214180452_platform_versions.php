<?php

use Phinx\Migration\AbstractMigration;

class PlatformVersions extends AbstractMigration {
    public function change(): void {
        $this->table('users_sessions')
            ->addColumn('BrowserVersion', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->addColumn('OperatingSystemVersion', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->update();
    }
}
