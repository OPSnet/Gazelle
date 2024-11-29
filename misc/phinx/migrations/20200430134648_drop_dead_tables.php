<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class DropDeadTables extends AbstractMigration {
    public function up(): void {
        $this->table('comments_edits_tmp')->drop()->save();
        $this->table('concerts')->drop()->save();
        $this->table('currency_conversion_rates')->drop()->save();
        $this->table('last_sent_email')->drop()->save();
        $this->table('new_info_hashes')->drop()->save();
        $this->table('ocelot_query_times')->drop()->save();
        $this->table('sphinx_hash')->drop()->save();
        $this->table('staff_ignored_questions')->drop()->save();
        $this->table('styles_backup')->drop()->save();
        $this->table('torrents_balance_history')->drop()->save();
        $this->table('users_points')->drop()->save();
        $this->table('users_points_requests')->drop()->save();
        $this->table('users_torrent_history_snatch')->drop()->save();
        $this->table('users_torrent_history_temp')->drop()->save();
        $this->table('upload_contest')->drop()->save();
    }

    public function down(): void {
        $this->table('comments_edits_tmp', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Page', 'enum', [
                'null' => true,
                'default' => null,
                'limit' => 8,
                'values' => ['forums', 'artist', 'collages', 'requests', 'torrents'],
            ])
            ->addColumn('PostID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('EditUser', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('EditTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['EditUser'], [
                'name' => 'EditUser',
                'unique' => false,
            ])
            ->addIndex(['Page', 'PostID', 'EditTime'], [
                'name' => 'PostHistory',
                'unique' => false,
            ])
            ->create();

        $this->table('concerts', [
                'id' => false,
                'primary_key' => ['ID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => true,
            ])
            ->addColumn('ConcertID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addIndex(['ConcertID'], [
                'name' => 'ConcertID',
                'unique' => false,
            ])
            ->addIndex(['TopicID'], [
                'name' => 'TopicID',
                'unique' => false,
            ])
            ->create();

        $this->table('currency_conversion_rates', [
                'id' => false,
                'primary_key' => ['Currency'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Currency', 'char', [
                'null' => false,
                'limit' => 3,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Rate', 'decimal', [
                'null' => true,
                'default' => null,
                'precision' => 9,
                'scale' => 4,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->create();

        $this->table('last_sent_email', [
                'id' => false,
                'primary_key' => ['UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->create();

        $this->table('new_info_hashes', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('InfoHash', 'binary', [
                'null' => true,
                'default' => null,
                'limit' => 20,
            ])
            ->addIndex(['InfoHash'], [
                'name' => 'InfoHash',
                'unique' => false,
            ])
            ->create();

        $this->table('ocelot_query_times', [
                'id' => false,
                'primary_key' => ['starttime'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('buffer', 'enum', [
                'null' => false,
                'limit' => 8,
                'values' => ['users', 'torrents', 'snatches', 'peers'],
            ])
            ->addColumn('starttime', 'datetime', [
                'null' => false,
            ])
            ->addColumn('ocelotinstance', 'datetime', [
                'null' => false,
            ])
            ->addColumn('querylength', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('timespent', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['starttime'], [
                'name' => 'starttime',
                'unique' => true,
            ])
            ->create();

        $this->table('sphinx_hash', [
                'id' => false,
                'primary_key' => ['ID'],
                'engine' => 'MyISAM',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('GroupName', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ArtistName', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 2048,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TagList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 728,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('CatalogueNumber', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RecordLabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Time', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
            ])
            ->addColumn('ReleaseType', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Size', 'biginteger', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Snatched', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Seeders', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Leechers', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('LogScore', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 3,
            ])
            ->addColumn('Scene', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('VanityHouse', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('HasLog', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('HasCue', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('FreeTorrent', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Media', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Format', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Encoding', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RemasterYear', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('RemasterTitle', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 512,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RemasterRecordLabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RemasterCatalogueNumber', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FileList', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();

        $this->table('staff_ignored_questions', [
                'id' => false,
                'primary_key' => ['QuestionID', 'UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('QuestionID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->create();

        $this->table('styles_backup', [
                'id' => false,
                'primary_key' => ['UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('StyleID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('StyleURL', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['StyleURL'], [
                'name' => 'StyleURL',
                'unique' => false,
            ])
            ->create();

        $this->table('torrents_balance_history', [
                'id' => false,
                'primary_key' => ['TorrentID', 'Time'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('balance', 'biginteger', [
                'null' => false,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Last', 'enum', [
                'null' => true,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->addIndex(['TorrentID', 'Time'], [
                'name' => 'TorrentID_2',
                'unique' => true,
            ])
            ->addIndex(['TorrentID', 'balance'], [
                'name' => 'TorrentID_3',
                'unique' => true,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();

        $this->table('users_points', [
                'id' => false,
                'primary_key' => ['UserID', 'GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Points', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->create();

        $this->table('users_points_requests', [
                'id' => false,
                'primary_key' => ['RequestID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('RequestID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Points', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['RequestID'], [
                'name' => 'RequestID',
                'unique' => false,
            ])
            ->create();

        $this->table('users_torrent_history_snatch', [
                'id' => false,
                'primary_key' => ['UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('NumSnatches', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex(['NumSnatches'], [
                'name' => 'NumSnatches',
                'unique' => false,
            ])
            ->create();

        $this->table('users_torrent_history_temp', [
                'id' => false,
                'primary_key' => ['UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('NumTorrents', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
                'signed' => false,
            ])
            ->addColumn('SumTime', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('SeedingAvg', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
                'signed' => false,
            ])
            ->create();

        $this->table('upload_contest', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
    }
}
