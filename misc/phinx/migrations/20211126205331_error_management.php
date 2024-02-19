<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ErrorManagement extends AbstractMigration
{
    public function change(): void
    {
        $this->table('error_log', ['id' => false, 'primary_key' => 'error_log_id', 'encoding' => 'utf8mb4'])
             ->addColumn('error_log_id', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
             ->addColumn('duration', 'float', ['default' => 0])
             ->addColumn('memory', 'biginteger', ['limit' => 10, 'signed' => false, 'default' => 0])
             ->addColumn('nr_query', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
             ->addColumn('nr_cache', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
             ->addColumn('seen', 'integer', ['limit' => 10, 'signed' => false, 'default' => 1])
             ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('updated', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->addColumn('digest', 'binary', ['limit' => 16])
             ->addColumn('uri', 'string', ['limit' => 255])
             ->addColumn('trace', 'text')
             ->addColumn('request', 'json')
             ->addColumn('error_list', 'json')
             ->addIndex(['digest'], ['unique' => true, 'name' => 'digest_uidx'])
             ->addIndex(['updated'], ['name' => 'updated_idx'])
             ->create();
    }
}
