<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TorrentReaper extends AbstractMigration {
    public function up(): void {
        $this->table('torrent_unseeded', ['id' => false, 'primary_key' => ['torrent_unseeded_id']])
            ->addColumn('torrent_unseeded_id', 'integer', ['identity' => true])
            ->addColumn('torrent_id',          'integer')
            ->addColumn('state',  'enum', ['default' => 'never', 'values' => ['never', 'unseeded']])
            ->addColumn('notify', 'enum', ['default' => 'initial', 'values' => ['initial', 'final']])
            ->addColumn('unseeded_date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('torrent_id', 'torrents', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['torrent_id'], ['name' => 'tu_t_uidx', 'unique' => true])
            ->addIndex(['unseeded_date'], ['name' => 'tu_ud_idx'])
            ->save();

        $this->table('torrent_unseeded_claim', ['id' => false, 'primary_key' => ['torrent_id', 'user_id']])
            ->addColumn('torrent_id', 'integer')
            ->addColumn('user_id',    'integer')
            ->addColumn('claim_date', 'datetime', ['null' => true])
            ->addForeignKey('torrent_id', 'torrents', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id'], ['name' => 'tuc_u_idx'])
            ->save();

        $this->execute("
            DELETE FROM periodic_task WHERE classname IN (
                'DeleteNeverSeededTorrents',
                'DeleteUnseededTorrents',
                'NotifyNonseedingUploaders'
            )
        ");

        $this->table('periodic_task')
             ->insert([
                'name'        => 'Torrent Reaper',
                'classname'   => 'Reaper',
                'description' => 'Process torrents that are unseeded/were never seeded',
                'period'      => 4 * 3600,
                'is_enabled'  => 0,
            ])
            ->save();

        $this->table('user_attr')->insert(
            [
                ['Name' => 'no-pm-unseeded-snatch', 'Description' => 'Do not receive system PMs on imminent removal of an unseeded snatch'],
                ['Name' => 'no-pm-unseeded-upload', 'Description' => 'Do not receive system PMs on imminent removal of an unseeded upload'],
            ]
        )->save();
    }

    public function down(): void {
        $this->table('torrent_unseeded')->drop()->save();
        $this->table('torrent_unseeded_claim')->drop()->save();

        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'Reaper'
        ");

        $this->execute("
            DELETE FROM user_attr where Name IN ('no-pm-unseeded-snatch', 'no-pm-unseeded-upload')"
        );

        $this->table('periodic_task')
            ->insert([
                [
                    'name' => 'Torrent Reaper - Never Seeded',
                    'classname' => 'DeleteNeverSeededTorrents',
                    'description' => 'Deletes torrents that were never seeded',
                    'period' => 3600 * 24
                ],
                [
                    'name' => 'Torrent Reaper - Unseeded',
                    'classname' => 'DeleteUnseededTorrents',
                    'description' => 'Deletes unseeded torrents',
                    'period' => 3600 * 24
                ],
                [
                    'name' => 'Unseeded Notifications',
                    'classname' => 'NotifyNonseedingUploaders',
                    'description' => 'Sends warnings for unseeded torrents',
                    'period' => 3600 * 24 * 7
                ],
            ])
            ->save();
    }
}
