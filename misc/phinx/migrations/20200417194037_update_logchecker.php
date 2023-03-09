<?php

use Phinx\Migration\AbstractMigration;
use OrpheusNET\Logchecker\Check\Checksum;

class UpdateLogchecker extends AbstractMigration {
    public function change(): void {
        $this->table('torrents_logs')
            ->addColumn('Ripper', 'string', ['default' => ''])
            ->addColumn('RipperVersion', 'string', ['null' => true])
            ->addColumn('Language', 'string', ['default' => 'en', 'limit' => 2])
            ->addColumn('ChecksumState', 'enum', [
                'default' => 'checksum_ok',
                'values' => [Checksum::CHECKSUM_OK, Checksum::CHECKSUM_MISSING, Checksum::CHECKSUM_INVALID]
            ])
            ->addColumn('LogcheckerVersion', 'string', ['default' => ''])
            ->update();
    }
}
