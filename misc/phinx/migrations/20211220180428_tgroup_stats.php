<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TgroupStats extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tgroup_summary', ['id' => false, 'primary_key' => 'tgroup_id'])
             ->addColumn('tgroup_id', 'integer', ['limit' => 10])
             ->addColumn('bookmark_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('download_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('leech_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('seeding_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('snatch_total', 'integer', ['limit' => 10, 'default' => 0])
             ->addForeignKey('tgroup_id', 'torrents_group', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();
    }
}
