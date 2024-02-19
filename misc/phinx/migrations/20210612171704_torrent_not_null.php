<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* see comment in misc/phinx/migrations/20200219075508_drop_secret.php */

final class TorrentNotNull extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('torrents')
            ->changeColumn('UserID', 'integer', ['length' => 10, 'null' => false])
            ->changeColumn('time', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->removeColumn('TranscodedFrom')
            ->save();
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('torrents')
            ->changeColumn('UserID', 'integer', ['length' => 10, 'null' => true])
            ->changeColumn('time', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('TranscodedFrom', 'integer', ['limit' => 10, 'default' => 0])
            ->save();
    }
}
