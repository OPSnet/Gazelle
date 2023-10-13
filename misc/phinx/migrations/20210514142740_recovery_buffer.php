<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RecoveryBuffer extends AbstractMigration
{
    public function change(): void
    {
        $this->table('recovery_buffer', ['id' => false, 'primary_key' => ['user_id']])
            ->addColumn('user_id',     'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('prev_id',     'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('uploaded',    'biginteger', ['limit' => 20])
            ->addColumn('downloaded',  'biginteger', ['limit' => 20])
            ->addColumn('bounty',      'biginteger', ['limit' => 20])
            ->addColumn('final',       'biginteger', ['limit' => 20])
            ->addColumn('nr_torrents', 'biginteger', ['limit' => 6])
            ->addColumn('userclass',   'string', ['limit' => 15])
            ->create();
    }
}
