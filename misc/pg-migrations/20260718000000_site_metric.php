<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SiteMetric extends AbstractMigration {
    public function up(): void {
        $this->table('site_metric', ['id' => false, 'primary_key' => 'id_site_metric'])
            ->addColumn('id_site_metric', 'integer', ['identity' => true])
            ->addColumn('name', 'string', ['length' => 30])
            ->addColumn('description', 'string', ['length' => 50])
            ->addIndex(['name'], ['name' => 'sm_n_uidx', 'unique' => true])
            ->insert([
                [
                    'description'   => 'Requests unfilled',
                    'name'          => 'request-unfilled',
                ],
                [
                    'description'   => 'Requests filled',
                    'name'          => 'request-filled',
                ],
                [
                    'description'   => 'Snatched Lossless',
                    'name'          => 'snatch-lossless',
                ],
                [
                    'description'   => 'Snatched Lossy',
                    'name'          => 'snatch-lossy',
                ],
                [
                    'description'   => 'Torrents created',
                    'name'          => 'torrent-created',
                ],
                [
                    'description'   => 'Torrents seeding',
                    'name'          => 'torrent-seeding',
                ],
                [
                    'description'   => 'Torrents leeching',
                    'name'          => 'torrent-leeching',
                ],
                [
                    'description'   => 'Single-seeded FLACs',
                    'name'          => 'torrent-single-seed-flac',
                ],
                [
                    'description'   => 'Users enabled',
                    'name'          => 'user-enable',
                ],
                [
                    'description'   => 'Users disabled',
                    'name'          => 'user-disable',
                ],
                [
                    'description'   => 'Users on ratio watch',
                    'name'          => 'user-ratio',
                ],
            ])
            ->save();

        $this->table('history_site_metric', ['id' => false, 'primary_key' => 'id_history_site_metric'])
            ->addColumn('id_history_site_metric', 'integer', ['identity' => true])
            ->addColumn('id_site_metric', 'integer')
            ->addColumn('metric', 'integer')
            ->addColumn('dt', 'date')
            ->addIndex(['id_site_metric', 'dt'], ['name' => 'hsm_m_dt_idx'])
            ->addForeignKey('id_site_metric', 'site_metric', 'id_site_metric', ['delete' => 'cascade', 'update' => 'cascade'])
            ->save();

        // note: run the task more than once a day to handle unavailability during site maintenance or deadlocks
        $this->table('task')
            ->insert([
                'name'        => 'Refresh history_site_metric',
                'class_name'  => 'HistorySiteMetric',
                'description' => 'Record yesterday\'s site metrics',
                'period'      => 86400 / 4,
            ])
            ->save();
    }

    public function down(): void {
        $this->query("
            delete from task where class_name = 'HistorySiteMetric'
        ");
        $this->query("
            drop table history_site_metric
        ");
        $this->query("
            drop table site_metric
        ");
    }
}
