<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InvisibleTorrentReport extends AbstractMigration {
    public function change(): void {
        $this->table('torrent_report_configuration')
            ->addColumn('is_invisible', 'boolean', ['default' => false])
            ->save();
        $this->execute("UPDATE torrent_report_configuration SET is_invisible = 1 WHERE type IN ('edited', 'urgent')");
    }
}
