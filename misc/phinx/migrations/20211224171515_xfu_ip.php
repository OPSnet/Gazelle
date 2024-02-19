<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class XfuIp extends AbstractMigration
{
    public function change(): void
    {
        $this->table('xbt_files_users')
            ->addIndex(['IP'], ['name' => 'xfu_ip_idx'])
            ->update();
    }
}
