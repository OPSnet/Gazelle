<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class NukeGeoip extends AbstractMigration
{
    public function up(): void {
        $this->table('geoip_country')->drop()->update();
        $this->execute("DELETE FROM periodic_task WHERE classname = 'UpdateGeoip'");
    }

    public function down(): void {
        $this->table('geoip_country', [
                'id' => false,
                'primary_key' => ['StartIP', 'EndIP'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('StartIP', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('EndIP', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('Code', 'string', [
                'null' => false,
                'limit' => 2,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();

        $this->table('periodic_task')
             ->insert([
                'name' => 'Update Geoip',
                'classname' => 'UpdateGeoip',
                'description' => 'Updates geoip distributions',
                'period' => 60 * 60 * 24,
                'is_enabled' => false
            ])->save();
    }
}
