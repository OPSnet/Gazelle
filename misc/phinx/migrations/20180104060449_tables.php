<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Tables extends AbstractMigration {
    public function down(): void {
        $this->execute("
SET FOREIGN_KEY_CHECKS = 0;
SET @tables = NULL;
SET GROUP_CONCAT_MAX_LEN=32768;

SELECT GROUP_CONCAT('`', table_schema, '`.`', table_name, '`') INTO @tables
FROM   information_schema.tables
WHERE  table_schema = (SELECT DATABASE()) AND table_name <> 'phinxlog';
SELECT IFNULL(@tables, '') INTO @tables;

SET        @tables = CONCAT('DROP TABLE IF EXISTS ', @tables);
PREPARE    stmt FROM @tables;
EXECUTE    stmt;
DEALLOCATE PREPARE stmt;
SET        FOREIGN_KEY_CHECKS = 1;
DROP FUNCTION binomial_ci;");
    }

    public function up(): void {
        // Message to future archeologists:
        // If this migration crashes half-way through due running it on a stricter
        // database engine, you can move forward by uncommenting the following line
        // and then iterating until things work.
        // $this->down();
        //
        // Another alternative is to connect to the mysql container and run 'drop database gazelle; create database gazelle;'

        $this->execute("ALTER DATABASE CHARACTER SET 'utf8';");
        $this->execute("ALTER DATABASE COLLATE='utf8_swedish_ci';");
        $this->execute("
CREATE FUNCTION IF NOT EXISTS binomial_ci(p int, n int) RETURNS float DETERMINISTIC
RETURN IF(n = 0,0.0,((p + 1.35336) / n - 1.6452 * SQRT((p * (n-p)) / n + 0.67668) / n) / (1 + 2.7067 / n));
");

        $this->table('lastfm_users', [
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
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Username', 'string', [
                'null' => false,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('push_notifications_usage', [
                'id' => false,
                'primary_key' => ['PushService'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('PushService', 'string', [
                'null' => false,
                'limit' => 10,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TimesUsed', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->create();
        $this->table('sphinx_requests_delta', [
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
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeAdded', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('LastVote', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TagList', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 728,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('ArtistList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 2048,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ReleaseType', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('CatalogueNumber', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('BitrateList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FormatList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MediaList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LogCue', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FillerID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeFilled', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('Visible', 'binary', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
            ])
            ->addColumn('Bounty', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('Votes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('RecordLabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'Userid',
                'unique' => false,
            ])
            ->addIndex(['Title'], [
                'name' => 'Name',
                'unique' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'Filled',
                'unique' => false,
            ])
            ->addIndex(['FillerID'], [
                'name' => 'FillerID',
                'unique' => false,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->addIndex(['Year'], [
                'name' => 'Year',
                'unique' => false,
            ])
            ->addIndex(['TimeFilled'], [
                'name' => 'TimeFilled',
                'unique' => false,
            ])
            ->addIndex(['LastVote'], [
                'name' => 'LastVote',
                'unique' => false,
            ])
            ->create();
        $this->table('donations_bitcoin', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('BitcoinAddress', 'string', [
                'null' => false,
                'limit' => 34,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Amount', 'decimal', [
                'null' => false,
                'precision' => 24,
                'scale' => 8,
            ])
            ->addIndex(['BitcoinAddress', 'Amount'], [
                'name' => 'BitcoinAddress',
                'unique' => false,
            ])
            ->create();
        $this->table('donor_rewards', [
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
            ->addColumn('IconMouseOverText', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AvatarMouseOverText', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('CustomIcon', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('SecondAvatar', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('CustomIconLink', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfo1', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfo2', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfo3', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfo4', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfoTitle1', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfoTitle2', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfoTitle3', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ProfileInfoTitle4', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('permissions', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('Level', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 25,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Values', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('DisplayStaff', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('PermittedForums', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 150,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Secondary', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['Level'], [
                'name' => 'Level',
                'unique' => true,
            ])
            ->addIndex(['DisplayStaff'], [
                'name' => 'DisplayStaff',
                'unique' => false,
            ])
            ->create();
        $this->table('users_freeleeches', [
                'id' => false,
                'primary_key' => ['UserID', 'TorrentID'],
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
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Expired', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Downloaded', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('Uses', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 10,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['Expired', 'Time'], [
                'name' => 'Expired_Time',
                'unique' => false,
            ])
            ->create();
        $this->table('wiki_articles', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Revision', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 10,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MinClassRead', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('MinClassEdit', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Author', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->create();
        $this->table('artists_similar_votes', [
                'id' => false,
                'primary_key' => ['SimilarID', 'UserID', 'Way'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('SimilarID', 'integer', [
                'null' => false,
                'limit' => 12,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Way', 'enum', [
                'null' => false,
                'default' => 'up',
                'limit' => 4,
                'values' => ['up', 'down'],
            ])
            ->create();
        $this->table('requests_votes', [
                'id' => false,
                'primary_key' => ['RequestID', 'UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('RequestID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Bounty', 'biginteger', [
                'null' => false,
                'signed' => false,
            ])
            ->addIndex(['RequestID'], [
                'name' => 'RequestID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Bounty'], [
                'name' => 'Bounty',
                'unique' => false,
            ])
            ->create();
        $this->table('users_notify_quoted', [
                'id' => false,
                'primary_key' => ['UserID', 'Page', 'PostID'],
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
            ->addColumn('QuoterID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Page', 'enum', [
                'null' => false,
                'limit' => 8,
                'values' => ['forums', 'artist', 'collages', 'requests', 'torrents'],
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('PostID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UnRead', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => true,
            ])
            ->create();
        $this->table('artists_similar', [
                'id' => false,
                'primary_key' => ['ArtistID', 'SimilarID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('SimilarID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 12,
            ])
            ->addIndex(['ArtistID', 'SimilarID'], [
                'name' => 'ArtistID',
                'unique' => false,
            ])
            ->create();
        $this->table('artists_alias', [
                'id' => false,
                'primary_key' => ['AliasID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('AliasID', 'integer', [
                'null' => false,
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Redirect', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex(['ArtistID', 'Name'], [
                'name' => 'ArtistID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_downloads', [
                'id' => false,
                'primary_key' => ['UserID', 'TorrentID', 'Time'],
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
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 1,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'TorrentID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('log', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('Message', 'string', [
                'null' => false,
                'limit' => 400,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_lossymaster_approved', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->create();
        $this->table('wiki_revisions', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Revision', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Date', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Author', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addIndex(['ID', 'Revision'], [
                'name' => 'ID_Revision',
                'unique' => false,
            ])
            ->create();
        $this->table('news', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('xbt_snatched', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('uid', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('tstamp', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('fid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('IP', 'string', [
                'null' => false,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('seedtime', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['fid'], [
                'name' => 'fid',
                'unique' => false,
            ])
            ->addIndex(['tstamp'], [
                'name' => 'tstamp',
                'unique' => false,
            ])
            ->addIndex(['uid', 'tstamp'], [
                'name' => 'uid_tstamp',
                'unique' => false,
            ])
            ->create();
        $this->table('forums_posts', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AuthorID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AddedTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('EditedUserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('EditedTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['TopicID'], [
                'name' => 'TopicID',
                'unique' => false,
            ])
            ->addIndex(['AuthorID'], [
                'name' => 'AuthorID',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_tags_votes', [
                'id' => false,
                'primary_key' => ['GroupID', 'TagID', 'UserID', 'Way'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('TagID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Way', 'enum', [
                'null' => false,
                'default' => 'up',
                'limit' => 4,
                'values' => ['up', 'down'],
            ])
            ->create();
        $this->table('users_notifications_settings', [
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
            ->addColumn('Inbox', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('StaffPM', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('News', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Blog', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Torrents', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Collages', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Quotes', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Subscriptions', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('SiteAlerts', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('RequestAlerts', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('CollageAlerts', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('TorrentAlerts', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('ForumAlerts', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('donor_forum_usernames', [
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
            ->addColumn('Prefix', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Suffix', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UseComma', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('users_sessions', [
                'id' => false,
                'primary_key' => ['UserID', 'SessionID'],
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
            ->addColumn('SessionID', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('KeepLogged', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Browser', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('OperatingSystem', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 13,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('IP', 'string', [
                'null' => false,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LastUpdate', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Active', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('FullUA', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['LastUpdate'], [
                'name' => 'LastUpdate',
                'unique' => false,
            ])
            ->addIndex(['Active'], [
                'name' => 'Active',
                'unique' => false,
            ])
            ->addIndex(['Active', 'LastUpdate', 'KeepLogged'], [
                'name' => 'ActiveAgeKeep',
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
        $this->table('users_comments_last_read', [
                'id' => false,
                'primary_key' => ['UserID', 'Page', 'PageID'],
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
            ->addColumn('Page', 'enum', [
                'null' => false,
                'limit' => 8,
                'values' => ['artist', 'collages', 'requests', 'torrents'],
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('PostID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addIndex(['Page', 'PageID'], [
                'name' => 'Page',
                'unique' => false,
            ])
            ->create();
        $this->table('users_dupes', [
                'id' => false,
                'primary_key' => ['UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
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
                'unique' => true,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_subscriptions', [
                'id' => false,
                'primary_key' => ['UserID', 'TopicID'],
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
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->create();
        $this->table('do_not_upload', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Comment', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Sequence', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_MEDIUM,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('xbt_client_whitelist', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('peer_id', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('vstring', 'string', [
                'null' => true,
                'default' => '',
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['peer_id'], [
                'name' => 'peer_id',
                'unique' => true,
            ])
            ->create();
        $this->table('sphinx_tg', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 300,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('tags', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_SMALL,
            ])
            ->addColumn('rlabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('cnumber', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('catid', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_SMALL,
            ])
            ->addColumn('reltype', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_SMALL,
            ])
            ->addColumn('vanityhouse', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('bookmarks_collages', [
                'id' => false,
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
            ->addColumn('CollageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['CollageID'], [
                'name' => 'CollageID',
                'unique' => false,
            ])
            ->create();
        $this->table('artists_group', [
                'id' => false,
                'primary_key' => ['ArtistID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RevisionID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
            ])
            ->addColumn('VanityHouse', 'boolean', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('LastCommentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addIndex(['Name', 'RevisionID'], [
                'name' => 'Name',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_files', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'MyISAM',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('File', 'blob', [
                'null' => false,
                'limit' => MysqlAdapter::BLOB_MEDIUM,
            ])
            ->create();
        $this->table('torrents_peerlists_compare', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'MyISAM',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'FIXED',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Seeders', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Leechers', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Snatches', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['TorrentID', 'Seeders', 'Leechers', 'Snatches'], [
                'name' => 'Stats',
                'unique' => false,
            ])
            ->create();
        $this->table('login_attempts', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('IP', 'string', [
                'null' => false,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LastAttempt', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Attempts', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('BannedUntil', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Bans', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['IP'], [
                'name' => 'IP',
                'unique' => false,
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
        $this->table('email_blacklist', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Comment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('users_enable_recommendations', [
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
                'limit' => 10,
            ])
            ->addColumn('Enable', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['Enable'], [
                'name' => 'Enable',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_bad_folders', [
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
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => false,
            ])
            ->create();
        $this->table('sphinx_delta', [
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
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
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
            ->addColumn('RemasterYear', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
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
            ->addColumn('Description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('VoteScore', 'float', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('LastChanged', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['Size'], [
                'name' => 'Size',
                'unique' => false,
            ])
            ->create();
        $this->table('users_history_ips', [
                'id' => false,
                'primary_key' => ['UserID', 'IP', 'StartTime'],
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
            ->addColumn('IP', 'string', [
                'null' => false,
                'default' => '0.0.0.0',
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('StartTime', 'datetime', [
                'null' => false,
            ])
            ->addColumn('EndTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['IP'], [
                'name' => 'IP',
                'unique' => false,
            ])
            ->addIndex(['StartTime'], [
                'name' => 'StartTime',
                'unique' => false,
            ])
            ->addIndex(['EndTime'], [
                'name' => 'EndTime',
                'unique' => false,
            ])
            ->create();
        $this->table('cover_art', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Image', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Summary', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['GroupID', 'Image'], [
                'name' => 'GroupID',
                'unique' => true,
            ])
            ->create();
        $this->table('pm_conversations', [
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
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('Subject', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('forums_topic_notes', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AuthorID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AddedTime', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['TopicID'], [
                'name' => 'TopicID',
                'unique' => false,
            ])
            ->addIndex(['AuthorID'], [
                'name' => 'AuthorID',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_bad_tags', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->create();
        $this->table('bookmarks_requests', [
                'id' => false,
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
            ->addColumn('Time', 'datetime', [
                'null' => false,
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
        $this->table('pm_messages', [
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
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('ConvID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 12,
            ])
            ->addColumn('SentDate', 'datetime', [
                'null' => true,
            ])
            ->addColumn('SenderID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['ConvID'], [
                'name' => 'ConvID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_collage_subs', [
                'id' => false,
                'primary_key' => ['UserID', 'CollageID'],
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
            ->addColumn('CollageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('LastVisit', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['CollageID'], [
                'name' => 'CollageID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_warnings_forums', [
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
            ->addColumn('Comment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('users_notify_torrents', [
                'id' => false,
                'primary_key' => ['UserID', 'TorrentID'],
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
            ->addColumn('FilterID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UnRead', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'TorrentID',
                'unique' => false,
            ])
            ->addIndex(['UserID', 'UnRead'], [
                'name' => 'UserID_Unread',
                'unique' => false,
            ])
            ->create();
        $this->table('sphinx_a', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('gid', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('aname', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['gid'], [
                'name' => 'gid',
                'unique' => false,
            ])
            ->create();
        $this->table('api_applications', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Token', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('ip_bans', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('FromIP', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('ToIP', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('Reason', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['FromIP', 'ToIP'], [
                'name' => 'FromIP_2',
                'unique' => true,
            ])
            ->addIndex(['ToIP'], [
                'name' => 'ToIP',
                'unique' => false,
            ])
            ->create();
        $this->table('xbt_files_users', [
                'id' => false,
                'primary_key' => ['uid', 'peer_id', 'fid'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('active', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('announced', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('completed', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('downloaded', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('remaining', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('uploaded', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('upspeed', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('downspeed', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('corrupt', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('timespent', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('useragent', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 51,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('connectable', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('peer_id', 'binary', [
                'null' => false,
                'default' => '00000000000000000000',
                'limit' => 20,
            ])
            ->addColumn('fid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('mtime', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('ip', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['remaining'], [
                'name' => 'remaining_idx',
                'unique' => false,
            ])
            ->addIndex(['fid'], [
                'name' => 'fid_idx',
                'unique' => false,
            ])
            ->addIndex(['mtime'], [
                'name' => 'mtime_idx',
                'unique' => false,
            ])
            ->addIndex(['uid', 'active'], [
                'name' => 'uid_active',
                'unique' => false,
            ])
            ->create();
        $this->table('tag_aliases', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('BadTag', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AliasTag', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['BadTag'], [
                'name' => 'BadTag',
                'unique' => false,
            ])
            ->addIndex(['AliasTag'], [
                'name' => 'AliasTag',
                'unique' => false,
            ])
            ->create();
        $this->table('site_history', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Url', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Category', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('SubCategory', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Tags', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AddedBy', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Date', 'datetime', [
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
            ->create();
        $this->table('artists_tags', [
                'id' => false,
                'primary_key' => ['TagID', 'ArtistID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TagID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('PositiveVotes', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 6,
            ])
            ->addColumn('NegativeVotes', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 6,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addIndex(['TagID', 'ArtistID', 'PositiveVotes', 'NegativeVotes', 'UserID'], [
                'name' => 'TagID',
                'unique' => false,
            ])
            ->create();
        $this->table('top10_history_torrents', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('HistoryID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Rank', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TitleString', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 150,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TagString', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('users_subscriptions_comments', [
                'id' => false,
                'primary_key' => ['UserID', 'Page', 'PageID'],
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
            ->addColumn('Page', 'enum', [
                'null' => false,
                'limit' => 8,
                'values' => ['artist', 'collages', 'requests', 'torrents'],
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->create();
        $this->table('group_log', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Info', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Hidden', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'TorrentID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('reportsv2', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('ReporterID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Type', 'string', [
                'null' => true,
                'default' => '',
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserComment', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ResolverID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Status', 'enum', [
                'null' => true,
                'default' => 'New',
                'limit' => 10,
                'values' => ['New', 'InProgress', 'Resolved'],
            ])
            ->addColumn('ReportedTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('LastChangeTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('ModComment', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Track', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Image', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ExtraID', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Link', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LogMessage', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Status'], [
                'name' => 'Status',
                'unique' => false,
            ])
            ->addIndex(['Type'], [
                'name' => 'Type',
                'unique' => false,
                'limit' => 1,
            ])
            ->addIndex(['LastChangeTime'], [
                'name' => 'LastChangeTime',
                'unique' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'TorrentID',
                'unique' => false,
            ])
            ->addIndex(['ResolverID'], [
                'name' => 'ResolverID',
                'unique' => false,
            ])
            ->create();
        $this->table('locked_accounts', [
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
            ->addColumn('Type', 'boolean', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('invite_tree', [
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
            ->addColumn('InviterID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TreePosition', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 8,
            ])
            ->addColumn('TreeID', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 10,
            ])
            ->addColumn('TreeLevel', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 3,
            ])
            ->addIndex(['InviterID'], [
                'name' => 'InviterID',
                'unique' => false,
            ])
            ->addIndex(['TreePosition'], [
                'name' => 'TreePosition',
                'unique' => false,
            ])
            ->addIndex(['TreeID'], [
                'name' => 'TreeID',
                'unique' => false,
            ])
            ->addIndex(['TreeLevel'], [
                'name' => 'TreeLevel',
                'unique' => false,
            ])
            ->create();
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
        $this->table('featured_merch', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ProductID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 35,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Image', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Started', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Ended', 'datetime', [
                'null' => true,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->create();
        $this->table('torrents_bad_files', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->create();
        $this->table('torrents_recommended', [
                'id' => false,
                'primary_key' => ['GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('site_options', [
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
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 64,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Value', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_TINY,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Comment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => true,
            ])
            ->addIndex(['Name'], [
                'name' => 'name_index',
                'unique' => false,
            ])
            ->create();
        $this->table('changelog', [
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
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Message', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Author', 'string', [
                'null' => false,
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('forums_last_read_topics', [
                'id' => false,
                'primary_key' => ['UserID', 'TopicID'],
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
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('PostID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addIndex(['TopicID'], [
                'name' => 'TopicID',
                'unique' => false,
            ])
            ->create();
        $this->table('stylesheets', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Description', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Default', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->create();
        $this->table('sphinx_requests', [
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
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeAdded', 'integer', [
                'null' => false,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('LastVote', 'integer', [
                'null' => false,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => false,
                'limit' => 3,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('ArtistList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 2048,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ReleaseType', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('CatalogueNumber', 'string', [
                'null' => false,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('BitrateList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FormatList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MediaList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LogCue', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FillerID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeFilled', 'integer', [
                'null' => false,
                'limit' => 12,
                'signed' => false,
            ])
            ->addColumn('Visible', 'binary', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
            ])
            ->addColumn('Bounty', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('Votes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('RecordLabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'Userid',
                'unique' => false,
            ])
            ->addIndex(['Title'], [
                'name' => 'Name',
                'unique' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'Filled',
                'unique' => false,
            ])
            ->addIndex(['FillerID'], [
                'name' => 'FillerID',
                'unique' => false,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->addIndex(['Year'], [
                'name' => 'Year',
                'unique' => false,
            ])
            ->addIndex(['TimeFilled'], [
                'name' => 'TimeFilled',
                'unique' => false,
            ])
            ->addIndex(['LastVote'], [
                'name' => 'LastVote',
                'unique' => false,
            ])
            ->create();
        $this->table('library_contest', [
                'id' => false,
                'primary_key' => ['UserID', 'TorrentID'],
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
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Points', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->create();
        $this->table('wiki_aliases', [
                'id' => false,
                'primary_key' => ['Alias'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Alias', 'string', [
                'null' => false,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('ArticleID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->create();
        $this->table('users_history_emails', [
                'id' => false,
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
            ->addColumn('Email', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('IP', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_push_notifications', [
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
            ->addColumn('PushService', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('PushOptions', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
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
        $this->table('artists_similar_scores', [
                'id' => false,
                'primary_key' => ['SimilarID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('SimilarID', 'integer', [
                'null' => false,
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('Score', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addIndex(['Score'], [
                'name' => 'Score',
                'unique' => false,
            ])
            ->create();
        $this->table('schedule', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('NextHour', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 2,
            ])
            ->addColumn('NextDay', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 2,
            ])
            ->addColumn('NextBiWeekly', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 2,
            ])
            ->create();
        $this->table('collages_artists', [
                'id' => false,
                'primary_key' => ['CollageID', 'ArtistID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('CollageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Sort', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('AddedOn', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Sort'], [
                'name' => 'Sort',
                'unique' => false,
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
        $this->table('requests_artists', [
                'id' => false,
                'primary_key' => ['RequestID', 'AliasID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('RequestID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AliasID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Importance', 'enum', [
                'null' => true,
                'default' => null,
                'limit' => 1,
                'values' => ['1', '2', '3', '4', '5', '6', '7'],
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
        $this->table('torrents_votes', [
                'id' => false,
                'primary_key' => ['GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Ups', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Total', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Score', 'float', [
                'null' => false,
                'default' => '0',
            ])
            ->addIndex(['Score'], [
                'name' => 'Score',
                'unique' => false,
            ])
            ->create();
        $this->table('forums', [
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
                'limit' => 6,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Sort', 'integer', [
                'null' => false,
                'limit' => 6,
                'signed' => false,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 40,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Description', 'string', [
                'null' => true,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MinClassRead', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('MinClassWrite', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('MinClassCreate', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('NumTopics', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('NumPosts', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('LastPostID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('LastPostAuthorID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('LastPostTopicID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('LastPostTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('AutoLock', 'enum', [
                'null' => true,
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('AutoLockWeeks', 'integer', [
                'null' => false,
                'default' => '4',
                'limit' => 3,
                'signed' => false,
            ])
            ->addIndex(['Sort'], [
                'name' => 'Sort',
                'unique' => false,
            ])
            ->addIndex(['MinClassRead'], [
                'name' => 'MinClassRead',
                'unique' => false,
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
        $this->table('reports_email_blacklist', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Type', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Checked', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('ResolverID', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('xbt_files_history', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('fid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('seedtime', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('downloaded', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('uploaded', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->create();
        $this->table('top10_history', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Type', 'enum', [
                'null' => true,
                'default' => null,
                'limit' => 6,
                'values' => ['Daily', 'Weekly'],
            ])
            ->create();
        $this->table('bookmarks_artists', [
                'id' => false,
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
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['ArtistID'], [
                'name' => 'ArtistID',
                'unique' => false,
            ])
            ->create();
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
        $this->table('pm_conversations_users', [
                'id' => false,
                'primary_key' => ['UserID', 'ConvID'],
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
            ->addColumn('ConvID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 12,
            ])
            ->addColumn('InInbox', 'enum', [
                'null' => false,
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('InSentbox', 'enum', [
                'null' => false,
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('SentDate', 'datetime', [
                'null' => true,
            ])
            ->addColumn('ReceivedDate', 'datetime', [
                'null' => true,
            ])
            ->addColumn('UnRead', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('Sticky', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('ForwardedTo', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 12,
            ])
            ->addIndex(['InInbox'], [
                'name' => 'InInbox',
                'unique' => false,
            ])
            ->addIndex(['InSentbox'], [
                'name' => 'InSentbox',
                'unique' => false,
            ])
            ->addIndex(['ConvID'], [
                'name' => 'ConvID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['SentDate'], [
                'name' => 'SentDate',
                'unique' => false,
            ])
            ->addIndex(['ReceivedDate'], [
                'name' => 'ReceivedDate',
                'unique' => false,
            ])
            ->addIndex(['Sticky'], [
                'name' => 'Sticky',
                'unique' => false,
            ])
            ->addIndex(['ForwardedTo'], [
                'name' => 'ForwardedTo',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_cassette_approved', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->create();
        $this->table('wiki_artists', [
                'id' => false,
                'primary_key' => ['RevisionID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('RevisionID', 'integer', [
                'null' => false,
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Summary', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Image', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['PageID'], [
                'name' => 'PageID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('staff_pm_responses', [
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
            ->addColumn('Message', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Name', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('collages_torrents', [
                'id' => false,
                'primary_key' => ['CollageID', 'GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('CollageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Sort', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('AddedOn', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Sort'], [
                'name' => 'Sort',
                'unique' => false,
            ])
            ->create();
        $this->table('bad_passwords', [
                'id' => false,
                'primary_key' => ['Password'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Password', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('comments', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Page', 'enum', [
                'null' => false,
                'limit' => 8,
                'values' => ['artist', 'collages', 'requests', 'torrents'],
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AuthorID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AddedTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('EditedUserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('EditedTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['Page', 'PageID'], [
                'name' => 'Page',
                'unique' => false,
            ])
            ->addIndex(['AuthorID'], [
                'name' => 'AuthorID',
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
        $this->table('forums_polls', [
                'id' => false,
                'primary_key' => ['TopicID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Question', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Answers', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Featured', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Closed', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
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
        $this->table('bookmarks_torrents', [
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
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Sort', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['GroupID', 'UserID'], [
                'name' => 'groups_users',
                'unique' => true,
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
        $this->table('staff_pm_messages', [
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
            ->addColumn('UserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('SentDate', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Message', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ConvID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->create();
        $this->table('requests', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => false,
            ])
            ->addColumn('LastVote', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => false,
                'limit' => 3,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('Image', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Description', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ReleaseType', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('CatalogueNumber', 'string', [
                'null' => false,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('BitrateList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FormatList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MediaList', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LogCue', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FillerID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('TimeFilled', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Visible', 'binary', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
            ])
            ->addColumn('RecordLabel', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('OCLC', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 55,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'Userid',
                'unique' => false,
            ])
            ->addIndex(['Title'], [
                'name' => 'Name',
                'unique' => false,
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'Filled',
                'unique' => false,
            ])
            ->addIndex(['FillerID'], [
                'name' => 'FillerID',
                'unique' => false,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->addIndex(['Year'], [
                'name' => 'Year',
                'unique' => false,
            ])
            ->addIndex(['TimeFilled'], [
                'name' => 'TimeFilled',
                'unique' => false,
            ])
            ->addIndex(['LastVote'], [
                'name' => 'LastVote',
                'unique' => false,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
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
        $this->table('sphinx_t', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('gid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('uid', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('size', 'biginteger', [
                'null' => false,
            ])
            ->addColumn('snatched', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('seeders', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('leechers', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('time', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('logscore', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_SMALL,
            ])
            ->addColumn('scene', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('haslog', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('hascue', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('freetorrent', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('media', 'string', [
                'null' => false,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('format', 'string', [
                'null' => false,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('encoding', 'string', [
                'null' => false,
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('remyear', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_SMALL,
            ])
            ->addColumn('remtitle', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('remrlabel', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('remcnumber', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('filelist', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('remident', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['gid', 'remident'], [
                'name' => 'gid_remident',
                'unique' => false,
            ])
            ->addIndex(['format'], [
                'name' => 'format',
                'unique' => false,
            ])
            ->create();
        $this->table('invites', [
                'id' => false,
                'primary_key' => ['InviteKey'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('InviterID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('InviteKey', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Expires', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Reason', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Expires'], [
                'name' => 'Expires',
                'unique' => false,
            ])
            ->addIndex(['InviterID'], [
                'name' => 'InviterID',
                'unique' => false,
            ])
            ->create();
        $this->table('blog', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('ThreadID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Important', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('users_votes', [
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
                'signed' => false,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Type', 'enum', [
                'null' => true,
                'default' => null,
                'limit' => 4,
                'values' => ['Up', 'Down'],
            ])
            ->addColumn('Time', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['Type'], [
                'name' => 'Type',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['Type', 'GroupID', 'UserID'], [
                'name' => 'Vote',
                'unique' => false,
            ])
            ->create();
        $this->table('wiki_torrents', [
                'id' => false,
                'primary_key' => ['RevisionID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('RevisionID', 'integer', [
                'null' => false,
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('PageID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Summary', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Image', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['PageID'], [
                'name' => 'PageID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('users_torrent_history', [
                'id' => false,
                'primary_key' => ['UserID', 'NumTorrents', 'Date'],
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
                'limit' => 6,
                'signed' => false,
            ])
            ->addColumn('Date', 'integer', [
                'null' => false,
                'limit' => 8,
                'signed' => false,
            ])
            ->addColumn('Time', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('LastTime', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
            ])
            ->addColumn('Finished', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('Weight', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addIndex(['Finished'], [
                'name' => 'Finished',
                'unique' => false,
            ])
            ->addIndex(['Date'], [
                'name' => 'Date',
                'unique' => false,
            ])
            ->create();
        $this->table('staff_blog', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('collages', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Description', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('NumTorrents', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('Deleted', 'enum', [
                'null' => true,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Locked', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 2,
            ])
            ->addColumn('TagList', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('MaxGroups', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('MaxGroupsPerUser', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Featured', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Subscribers', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('updated', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['CategoryID'], [
                'name' => 'CategoryID',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_logs', [
                'id' => false,
                'primary_key' => ['LogID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('LogID', 'integer', [
                'null' => false,
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Log', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FileName', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Details', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Score', 'integer', [
                'null' => false,
                'limit' => 3,
            ])
            ->addColumn('Checksum', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Adjusted', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('AdjustedScore', 'integer', [
                'null' => false,
                'limit' => 3,
            ])
            ->addColumn('AdjustedChecksum', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('AdjustedBy', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('AdjustmentReason', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AdjustmentDetails', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['TorrentID'], [
                'name' => 'TorrentID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_info', [
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
            ->addColumn('StyleID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('StyleURL', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Info', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Avatar', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AdminComment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('SiteOptions', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ViewAvatars', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Donor', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Artist', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DownloadAlt', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('Warned', 'datetime', [
                'null' => false,
            ])
            ->addColumn('SupportFor', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TorrentGrouping', 'enum', [
                'null' => false,
                'limit' => 1,
                'values' => ['0', '1', '2'],
                'comment' => '0=Open,1=Closed,2=Off',
            ])
            ->addColumn('ShowTags', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('NotifyOnQuote', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->addColumn('AuthKey', 'string', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ResetKey', 'string', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ResetExpires', 'datetime', [
                'null' => true,
            ])
            ->addColumn('JoinDate', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Inviter', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('BitcoinAddress', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 34,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('WarnedTimes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 2,
            ])
            ->addColumn('DisableAvatar', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableInvites', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisablePosting', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableForums', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisablePoints', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableIRC', 'enum', [
                'null' => true,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableTagging', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableUpload', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisableWiki', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('DisablePM', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('RatioWatchEnds', 'datetime', [
                'null' => true,
            ])
            ->addColumn('RatioWatchDownload', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('RatioWatchTimes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('BanDate', 'datetime', [
                'null' => true,
            ])
            ->addColumn('BanReason', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2', '3', '4'],
            ])
            ->addColumn('CatchupTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('LastReadNews', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('HideCountryChanges', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('RestrictedForums', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 150,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('DisableRequests', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('PermittedForums', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 150,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UnseededAlerts', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('LastReadBlog', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('InfoTitle', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => true,
            ])
            ->addIndex(['SupportFor'], [
                'name' => 'SupportFor',
                'unique' => false,
            ])
            ->addIndex(['DisableInvites'], [
                'name' => 'DisableInvites',
                'unique' => false,
            ])
            ->addIndex(['Donor'], [
                'name' => 'Donor',
                'unique' => false,
            ])
            ->addIndex(['Warned'], [
                'name' => 'Warned',
                'unique' => false,
            ])
            ->addIndex(['JoinDate'], [
                'name' => 'JoinDate',
                'unique' => false,
            ])
            ->addIndex(['Inviter'], [
                'name' => 'Inviter',
                'unique' => false,
            ])
            ->addIndex(['RatioWatchEnds'], [
                'name' => 'RatioWatchEnds',
                'unique' => false,
            ])
            ->addIndex(['RatioWatchDownload'], [
                'name' => 'RatioWatchDownload',
                'unique' => false,
            ])
            ->addIndex(['BitcoinAddress'], [
                'name' => 'BitcoinAddress',
                'unique' => false,
                'limit' => 4,
            ])
            ->addIndex(['AuthKey'], [
                'name' => 'AuthKey',
                'unique' => false,
            ])
            ->addIndex(['ResetKey'], [
                'name' => 'ResetKey',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_group', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('CategoryID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 3,
            ])
            ->addColumn('Name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 300,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Year', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('CatalogueNumber', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RecordLabel', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ReleaseType', 'integer', [
                'null' => true,
                'default' => '21',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('TagList', 'string', [
                'null' => false,
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('RevisionID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 12,
            ])
            ->addColumn('WikiBody', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('WikiImage', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('VanityHouse', 'boolean', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['ArtistID'], [
                'name' => 'ArtistID',
                'unique' => false,
            ])
            ->addIndex(['CategoryID'], [
                'name' => 'CategoryID',
                'unique' => false,
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => false,
                'limit' => 255,
            ])
            ->addIndex(['Year'], [
                'name' => 'Year',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['RevisionID'], [
                'name' => 'RevisionID',
                'unique' => false,
            ])
            ->create();
        $this->table('requests_tags', [
                'id' => false,
                'primary_key' => ['TagID', 'RequestID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TagID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('RequestID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addIndex(['TagID'], [
                'name' => 'TagID',
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
        $this->table('staff_answers', [
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
            ->addColumn('Answer', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Date', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('dupe_groups', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('Comments', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('api_users', [
                'id' => false,
                'primary_key' => ['UserID', 'AppID'],
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
            ->addColumn('AppID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Token', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('State', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->addColumn('Time', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('Access', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('users_geodistribution', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Code', 'string', [
                'null' => false,
                'limit' => 2,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Users', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->create();
        $this->table('featured_albums', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('ThreadID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 35,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Started', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Ended', 'datetime', [
                'null' => true,
            ])
            ->create();
        $this->table('users_main', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('Username', 'string', [
                'null' => false,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('PassHash', 'string', [
                'null' => false,
                'limit' => 60,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Secret', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('IRCKey', 'char', [
                'null' => true,
                'default' => null,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LastLogin', 'datetime', [
                'null' => true,
            ])
            ->addColumn('LastAccess', 'datetime', [
                'null' => true,
            ])
            ->addColumn('IP', 'string', [
                'null' => false,
                'default' => '0.0.0.0',
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Class', 'integer', [
                'null' => false,
                'default' => '5',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Uploaded', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('Downloaded', 'biginteger', [
                'null' => false,
                'default' => '0',
                'signed' => false,
            ])
            ->addColumn('BonusPoints', 'float', [
                'null' => false,
                'default' => '0.00000',
            ])
            ->addColumn('Title', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Enabled', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->addColumn('Paranoia', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Visible', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('Invites', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('PermissionID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('CustomPermissions', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('can_leech', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('torrent_pass', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RequiredRatio', 'double', [
                'null' => false,
                'default' => '0.00000000',
            ])
            ->addColumn('RequiredRatioWork', 'double', [
                'null' => false,
                'default' => '0.00000000',
            ])
            ->addColumn('ipcc', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 2,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FLTokens', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('FLT_Given', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Invites_Given', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('2FA_Key', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 16,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Recovery', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Username'], [
                'name' => 'Username',
                'unique' => true,
            ])
            ->addIndex(['Email'], [
                'name' => 'Email',
                'unique' => false,
            ])
            ->addIndex(['PassHash'], [
                'name' => 'PassHash',
                'unique' => false,
            ])
            ->addIndex(['LastAccess'], [
                'name' => 'LastAccess',
                'unique' => false,
            ])
            ->addIndex(['IP'], [
                'name' => 'IP',
                'unique' => false,
            ])
            ->addIndex(['Class'], [
                'name' => 'Class',
                'unique' => false,
            ])
            ->addIndex(['Uploaded'], [
                'name' => 'Uploaded',
                'unique' => false,
            ])
            ->addIndex(['Downloaded'], [
                'name' => 'Downloaded',
                'unique' => false,
            ])
            ->addIndex(['Enabled'], [
                'name' => 'Enabled',
                'unique' => false,
            ])
            ->addIndex(['Invites'], [
                'name' => 'Invites',
                'unique' => false,
            ])
            ->addIndex(['torrent_pass'], [
                'name' => 'torrent_pass',
                'unique' => false,
            ])
            ->addIndex(['RequiredRatio'], [
                'name' => 'RequiredRatio',
                'unique' => false,
            ])
            ->addIndex(['ipcc'], [
                'name' => 'cc_index',
                'unique' => false,
            ])
            ->addIndex(['PermissionID'], [
                'name' => 'PermissionID',
                'unique' => false,
            ])
            ->create();
        $this->table('staff_pm_conversations', [
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
            ->addColumn('Subject', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Status', 'enum', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'values' => ['Open', 'Unanswered', 'Resolved'],
            ])
            ->addColumn('Level', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('AssignedToUser', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Unread', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('ResolverID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['Status', 'AssignedToUser'], [
                'name' => 'StatusAssigned',
                'unique' => false,
            ])
            ->addIndex(['Status', 'Level'], [
                'name' => 'StatusLevel',
                'unique' => false,
            ])
            ->create();
        $this->table('users_levels', [
                'id' => false,
                'primary_key' => ['UserID', 'PermissionID'],
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
            ->addColumn('PermissionID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex(['PermissionID'], [
                'name' => 'PermissionID',
                'unique' => false,
            ])
            ->create();
        $this->table('forums_topics', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Title', 'string', [
                'null' => false,
                'limit' => 150,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AuthorID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('IsLocked', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('IsSticky', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('ForumID', 'integer', [
                'null' => false,
                'limit' => 3,
            ])
            ->addColumn('NumPosts', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('LastPostID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('LastPostTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('LastPostAuthorID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('StickyPostID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('Ranking', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('CreatedTime', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['AuthorID'], [
                'name' => 'AuthorID',
                'unique' => false,
            ])
            ->addIndex(['ForumID'], [
                'name' => 'ForumID',
                'unique' => false,
            ])
            ->addIndex(['IsSticky'], [
                'name' => 'IsSticky',
                'unique' => false,
            ])
            ->addIndex(['LastPostID'], [
                'name' => 'LastPostID',
                'unique' => false,
            ])
            ->addIndex(['Title'], [
                'name' => 'Title',
                'unique' => false,
            ])
            ->addIndex(['CreatedTime'], [
                'name' => 'CreatedTime',
                'unique' => false,
            ])
            ->create();
        $this->table('forums_polls_votes', [
                'id' => false,
                'primary_key' => ['TopicID', 'UserID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TopicID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Vote', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('tags', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('TagType', 'enum', [
                'null' => false,
                'default' => 'other',
                'limit' => 5,
                'values' => ['genre', 'other'],
            ])
            ->addColumn('Uses', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 12,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addIndex(['Name'], [
                'name' => 'Name_2',
                'unique' => true,
            ])
            ->addIndex(['TagType'], [
                'name' => 'TagType',
                'unique' => false,
            ])
            ->addIndex(['Uses'], [
                'name' => 'Uses',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('contest_leaderboard', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ContestID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('FlacCount', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('LastTorrentID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('LastTorrentName', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_swedish_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ArtistList', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_swedish_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ArtistNames', 'string', [
                'null' => false,
                'limit' => 200,
                'collation' => 'utf8_swedish_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('LastUpload', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['ContestID'], [
                'name' => 'contest_fk',
                'unique' => false,
            ])
            ->addIndex(['FlacCount', 'LastUpload', 'UserID'], [
                'name' => 'flac_upload_idx',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_lossyweb_approved', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->create();
        $this->table('forums_categories', [
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
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 40,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Sort', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
                'signed' => false,
            ])
            ->addIndex(['Sort'], [
                'name' => 'Sort',
                'unique' => false,
            ])
            ->create();
        $this->table('contest', [
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
            ->addColumn('ContestTypeID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 80,
                'collation' => 'utf8_swedish_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Banner', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 128,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('DateBegin', 'datetime', [
                'null' => false,
            ])
            ->addColumn('DateEnd', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Display', 'integer', [
                'null' => false,
                'default' => '50',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('MaxTracked', 'integer', [
                'null' => false,
                'default' => '500',
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('WikiText', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => true,
            ])
            ->addIndex(['ContestTypeID'], [
                'name' => 'contest_type_fk',
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
        $this->table('drives', [
                'id' => false,
                'primary_key' => ['DriveID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('DriveID', 'integer', [
                'null' => false,
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 50,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Offset', 'string', [
                'null' => false,
                'limit' => 10,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Media', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Format', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Encoding', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Remastered', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('RemasterYear', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
            ])
            ->addColumn('RemasterTitle', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RemasterCatalogueNumber', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RemasterRecordLabel', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 80,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Scene', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('HasLog', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('HasCue', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('HasLogDB', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('LogScore', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
            ])
            ->addColumn('LogChecksum', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->addColumn('info_hash', 'blob', [
                'null' => false,
                'limit' => MysqlAdapter::BLOB_REGULAR,
            ])
            ->addColumn('FileCount', 'integer', [
                'null' => false,
                'limit' => 6,
            ])
            ->addColumn('FileList', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FilePath', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Size', 'biginteger', [
                'null' => false,
                'limit' => 12,
            ])
            ->addColumn('Leechers', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
            ])
            ->addColumn('Seeders', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
            ])
            ->addColumn('last_action', 'datetime', [
                'null' => true,
            ])
            ->addColumn('FreeTorrent', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->addColumn('FreeLeechType', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2', '3', '4', '5', '6', '7'],
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Snatched', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('balance', 'biginteger', [
                'null' => false,
                'default' => '0',
            ])
            ->addColumn('LastReseedRequest', 'datetime', [
                'null' => true,
            ])
            ->addColumn('TranscodedFrom', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addIndex(['info_hash'], [
                'name' => 'InfoHash',
                'unique' => true,
                'limit' => 40,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Media'], [
                'name' => 'Media',
                'unique' => false,
            ])
            ->addIndex(['Format'], [
                'name' => 'Format',
                'unique' => false,
            ])
            ->addIndex(['Encoding'], [
                'name' => 'Encoding',
                'unique' => false,
            ])
            ->addIndex(['RemasterYear'], [
                'name' => 'Year',
                'unique' => false,
            ])
            ->addIndex(['FileCount'], [
                'name' => 'FileCount',
                'unique' => false,
            ])
            ->addIndex(['Size'], [
                'name' => 'Size',
                'unique' => false,
            ])
            ->addIndex(['Seeders'], [
                'name' => 'Seeders',
                'unique' => false,
            ])
            ->addIndex(['Leechers'], [
                'name' => 'Leechers',
                'unique' => false,
            ])
            ->addIndex(['Snatched'], [
                'name' => 'Snatched',
                'unique' => false,
            ])
            ->addIndex(['last_action'], [
                'name' => 'last_action',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['FreeTorrent'], [
                'name' => 'FreeTorrent',
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
        $this->table('users_notify_filters', [
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
                'limit' => 12,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Label', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 128,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Artists', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('RecordLabels', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Users', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Tags', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('NotTags', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Categories', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Formats', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Encodings', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Media', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('FromYear', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('ToYear', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 4,
            ])
            ->addColumn('ExcludeVA', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('NewGroupsOnly', 'enum', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'values' => ['1', '0'],
            ])
            ->addColumn('ReleaseTypes', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['FromYear'], [
                'name' => 'FromYear',
                'unique' => false,
            ])
            ->addIndex(['ToYear'], [
                'name' => 'ToYear',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_artists', [
                'id' => false,
                'primary_key' => ['GroupID', 'ArtistID', 'Importance'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('AliasID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Importance', 'enum', [
                'null' => false,
                'default' => '1',
                'limit' => 1,
                'values' => ['1', '2', '3', '4', '5', '6', '7'],
            ])
            ->addIndex(['ArtistID'], [
                'name' => 'ArtistID',
                'unique' => false,
            ])
            ->addIndex(['AliasID'], [
                'name' => 'AliasID',
                'unique' => false,
            ])
            ->addIndex(['Importance'], [
                'name' => 'Importance',
                'unique' => false,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->create();
        $this->table('users_history_passwords', [
                'id' => false,
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
            ->addColumn('ChangeTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('ChangerIP', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID', 'ChangeTime'], [
                'name' => 'User_Time',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_missing_lineage', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TimeAdded', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['TimeAdded'], [
                'name' => 'TimeAdded',
                'unique' => false,
            ])
            ->create();
        $this->table('torrents_peerlists', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'MyISAM',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'FIXED',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Seeders', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Leechers', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('Snatches', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['TorrentID', 'Seeders', 'Leechers', 'Snatches'], [
                'name' => 'Stats',
                'unique' => false,
            ])
            ->create();
        $this->table('reports', [
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
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('ThingID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Type', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Comment', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ResolverID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Status', 'enum', [
                'null' => true,
                'default' => 'New',
                'limit' => 10,
                'values' => ['New', 'InProgress', 'Resolved'],
            ])
            ->addColumn('ResolvedTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('ReportedTime', 'datetime', [
                'null' => true,
            ])
            ->addColumn('Reason', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ClaimerID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Notes', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Status'], [
                'name' => 'Status',
                'unique' => false,
            ])
            ->addIndex(['Type'], [
                'name' => 'Type',
                'unique' => false,
            ])
            ->addIndex(['ResolvedTime'], [
                'name' => 'ResolvedTime',
                'unique' => false,
            ])
            ->addIndex(['ResolverID'], [
                'name' => 'ResolverID',
                'unique' => false,
            ])
            ->create();
        $this->table('user_questions', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Question', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Date', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['Date'], [
                'name' => 'Date',
                'unique' => false,
            ])
            ->create();
        $this->table('donations', [
                'id' => false,
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
            ->addColumn('Amount', 'decimal', [
                'null' => false,
                'precision' => 6,
                'scale' => 2,
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('Currency', 'string', [
                'null' => false,
                'default' => 'USD',
                'limit' => 5,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Source', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 30,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Reason', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Rank', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('AddedBy', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('TotalRank', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->addIndex(['Amount'], [
                'name' => 'Amount',
                'unique' => false,
            ])
            ->create();
        $this->table('sphinx_index_last_pos', [
                'id' => false,
                'primary_key' => ['Type'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('Type', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 16,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->create();
        $this->table('friends', [
                'id' => false,
                'primary_key' => ['UserID', 'FriendID'],
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
            ->addColumn('FriendID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Comment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => false,
            ])
            ->addIndex(['FriendID'], [
                'name' => 'FriendID',
                'unique' => false,
            ])
            ->create();
        $this->table('calendar', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Category', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('StartDate', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('EndDate', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('AddedBy', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Importance', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Team', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
        $this->table('label_aliases', [
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
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('BadLabel', 'string', [
                'null' => false,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('AliasLabel', 'string', [
                'null' => false,
                'limit' => 100,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['BadLabel'], [
                'name' => 'BadLabel',
                'unique' => false,
            ])
            ->addIndex(['AliasLabel'], [
                'name' => 'AliasLabel',
                'unique' => false,
            ])
            ->create();
        $this->table('users_history_passkeys', [
                'id' => false,
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
            ->addColumn('OldPassKey', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('NewPassKey', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('ChangeTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('ChangerIP', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->create();
        $this->table('forums_specific_rules', [
                'id' => false,
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ForumID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 6,
                'signed' => false,
            ])
            ->addColumn('ThreadID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->create();
        $this->table('users_donor_ranks', [
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
            ->addColumn('Rank', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('DonationTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Hidden', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('TotalRank', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('SpecialRank', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('InvitesRecievedRank', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('RankExpirationTime', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['DonationTime'], [
                'name' => 'DonationTime',
                'unique' => false,
            ])
            ->addIndex(['SpecialRank'], [
                'name' => 'SpecialRank',
                'unique' => false,
            ])
            ->addIndex(['Rank'], [
                'name' => 'Rank',
                'unique' => false,
            ])
            ->addIndex(['TotalRank'], [
                'name' => 'TotalRank',
                'unique' => false,
            ])
            ->create();
        $this->table('users_enable_requests', [
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
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Email', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('IP', 'string', [
                'null' => false,
                'default' => '0.0.0.0',
                'limit' => 15,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('UserAgent', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Timestamp', 'datetime', [
                'null' => false,
            ])
            ->addColumn('HandledTimestamp', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('Token', 'char', [
                'null' => true,
                'default' => null,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('CheckedBy', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('Outcome', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => '1 for approved, 2 for denied, 3 for discarded',
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserId',
                'unique' => false,
            ])
            ->addIndex(['CheckedBy'], [
                'name' => 'CheckedBy',
                'unique' => false,
            ])
            ->create();
        $this->table('staff_blog_visits', [
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
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
                'unique' => true,
            ])
            ->create();
        $this->table('comments_edits', [
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
        $this->table('torrents_tags', [
                'id' => false,
                'primary_key' => ['TagID', 'GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TagID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
            ])
            ->addColumn('PositiveVotes', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 6,
            ])
            ->addColumn('NegativeVotes', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 6,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addIndex(['TagID'], [
                'name' => 'TagID',
                'unique' => false,
            ])
            ->addIndex(['GroupID'], [
                'name' => 'GroupID',
                'unique' => false,
            ])
            ->addIndex(['PositiveVotes'], [
                'name' => 'PositiveVotes',
                'unique' => false,
            ])
            ->addIndex(['NegativeVotes'], [
                'name' => 'NegativeVotes',
                'unique' => false,
            ])
            ->addIndex(['UserID'], [
                'name' => 'UserID',
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
        $this->table('contest_type', [
                'id' => false,
                'primary_key' => ['ID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_swedish_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => true,
            ])
            ->addColumn('Name', 'string', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_swedish_ci',
                'encoding' => 'utf8',
            ])
            ->addIndex(['Name'], [
                'name' => 'Name',
                'unique' => true,
            ])
            ->create();

        $this->table('contest_type')->insert([
          ['Name' => 'upload_flac'], ['Name' => 'request_fill']
        ])->save();

        $this->table('forums_categories')->insert([
            ['ID' => 1, 'Name' => 'Site', 'Sort' => 1],
            ['ID' => 21, 'Name' => 'Suggestions', 'Sort' => 3],
            ['ID' => 5, 'Name' => 'Community', 'Sort' => 5],
            ['ID' => 8, 'Name' => 'Music', 'Sort' => 8],
            ['ID' => 10, 'Name' => 'Help', 'Sort' => 10],
            ['ID' => 20, 'Name' => 'Trash', 'Sort' => 20]
          ])->save();

        $this->table('forums')->insert([
            ['ID' => 7, 'CategoryID' => 1, 'Sort' => 100, 'Name' => 'Pharmacy', 'Description' => 'Get your medication dispensed here', 'MinClassRead' => 1000, 'MinClassWrite' => 1000, 'MinClassCreate' => 1000],
            ['ID' => 5, 'CategoryID' => 1, 'Sort' => 200, 'Name' => 'Staff', 'Description' => 'No place like home', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 35, 'CategoryID' => 1, 'Sort' => 250, 'Name' => 'Developers', 'Description' => 'Developers forum', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 33, 'CategoryID' => 1, 'Sort' => 750, 'Name' => 'Designers', 'Description' => 'Designers', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 28, 'CategoryID' => 1, 'Sort' => 800, 'Name' => 'First Line Support', 'Description' => 'Special Support Operations Command (SSOC)', 'MinClassRead' => 900, 'MinClassWrite' => 900, 'MinClassCreate' => 900],
            ['ID' => 30, 'CategoryID' => 1, 'Sort' => 900, 'Name' => 'Interviewers', 'Description' => 'The Candidates', 'MinClassRead' => 900, 'MinClassWrite' => 900, 'MinClassCreate' => 900],

            ['ID' => 31, 'CategoryID' => 1, 'Sort' => 1000, 'Name' => 'Charlie Team', 'Description' => 'Quality Assurance', 'MinClassRead' => 850, 'MinClassWrite' => 850, 'MinClassCreate' => 850],
            ['ID' => 1, 'CategoryID' => 1, 'Sort' => 300, 'Name' => 'Orpheus', 'Description' => 'orpheus.network', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 12, 'CategoryID' => 1, 'Sort' => 600, 'Name' => 'Announcements', 'Description' => 'Public service announcements', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 800],
            ['ID' => 6, 'CategoryID' => 1, 'Sort' => 400, 'Name' => 'Bugs', 'Description' => 'I found a non critical bug', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 24, 'CategoryID' => 5, 'Sort' => 2000, 'Name' => 'Projects', 'Description' => 'I\'m working on a project', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

            ['ID' => 13, 'CategoryID' => 21, 'Sort' => 2990, 'Name' => 'Suggestions', 'Description' => 'I have an idea', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 36, 'CategoryID' => 21, 'Sort' => 3000, 'Name' => 'Approved', 'Description' => 'Self explanatory...', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 800],
            ['ID' => 37, 'CategoryID' => 21, 'Sort' => 3400, 'Name' => 'Implemented', 'Description' => 'The Suggestion I made has been implemented', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 15, 'CategoryID' => 21, 'Sort' => 3500, 'Name' => 'Denied', 'Description' => 'The Suggestion I made has been denied', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],

            ['ID' => 2, 'CategoryID' => 5, 'Sort' => 1200, 'Name' => 'Chat', 'Description' => 'General chat', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 25, 'CategoryID' => 5, 'Sort' => 2100, 'Name' => 'Games', 'Description' => 'I\'m a gamer', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 27, 'CategoryID' => 5, 'Sort' => 1100, 'Name' => 'Serious Discussions', 'Description' => 'The Library', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 29, 'CategoryID' => 5, 'Sort' => 1300, 'Name' => 'Power User', 'Description' => 'PU Forum <3', 'MinClassRead' => 200, 'MinClassWrite' => 200, 'MinClassCreate' => 200],
            ['ID' => 11, 'CategoryID' => 5, 'Sort' => 1600, 'Name' => 'Elites', 'Description' => 'I\'m 1337', 'MinClassRead' => 250, 'MinClassWrite' => 250, 'MinClassCreate' => 250],
            ['ID' => 40, 'CategoryID' => 5, 'Sort' => 1610, 'Name' => 'Torrent Masters', 'Description' => 'The Holy Grail', 'MinClassRead' => 400, 'MinClassWrite' => 400, 'MinClassCreate' => 400],
            ['ID' => 38, 'CategoryID' => 5, 'Sort' => 1650, 'Name' => 'VIP', 'Description' => 'Very Important Phorum', 'MinClassRead' => 601, 'MinClassWrite' => 601, 'MinClassCreate' => 601],
            ['ID' => 10, 'CategoryID' => 5, 'Sort' => 1500, 'Name' => 'Donors', 'Description' => 'I have a heart', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 39, 'CategoryID' => 5, 'Sort' => 1670, 'Name' => 'Invitations', 'Description' => 'Stairway to Heaven', 'MinClassRead' => 250, 'MinClassWrite' => 250, 'MinClassCreate' => 250],
            ['ID' => 22, 'CategoryID' => 5, 'Sort' => 1800, 'Name' => 'Comics', 'Description' => 'I read comics', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 23, 'CategoryID' => 5, 'Sort' => 1900, 'Name' => 'Technology', 'Description' => 'I like technology', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

            ['ID' => 8, 'CategoryID' => 8, 'Sort' => 30, 'Name' => 'Music', 'Description' => 'For the masses', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 18, 'CategoryID' => 8, 'Sort' => 31, 'Name' => 'Vanity House', 'Description' => 'I have some of my work to share', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 32, 'CategoryID' => 8, 'Sort' => 20, 'Name' => 'Audiophile', 'Description' => 'For the audiophile', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 19, 'CategoryID' => 8, 'Sort' => 32, 'Name' => 'The Studio', 'Description' => 'I\'m a DJ', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 26, 'CategoryID' => 8, 'Sort' => 34, 'Name' => 'Vinyl', 'Description' => 'Vinyl \'s are here to stay', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 20, 'CategoryID' => 8, 'Sort' => 33, 'Name' => 'Offered', 'Description' => 'I have some music to offer', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 9, 'CategoryID' => 5, 'Sort' => 1400, 'Name' => 'Artists', 'Description' => 'For the artistics', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 21, 'CategoryID' => 5, 'Sort' => 1700, 'Name' => 'Concerts and Events', 'Description' => 'I\'m off to a gig', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

            ['ID' => 3, 'CategoryID' => 10, 'Sort' => 40, 'Name' => 'Help!', 'Description' => 'I fell down and I cant get up', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 34, 'CategoryID' => 10, 'Sort' => 41, 'Name' => 'Editing', 'Description' => 'Quality Control', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 16, 'CategoryID' => 10, 'Sort' => 42, 'Name' => 'Tutorials', 'Description' => 'I would like to share my knowledge', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
            ['ID' => 17, 'CategoryID' => 10, 'Sort' => 43, 'Name' => 'BitTorrent', 'Description' => 'I need to talk about BitTorrent', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

            ['ID' => 4, 'CategoryID' => 20, 'Sort' => 5, 'Name' => 'Trash', 'Description' => 'Every thread ends up here eventually', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
            ['ID' => 14, 'CategoryID' => 20, 'Sort' => 101, 'Name' => 'Resolved Bugs', 'Description' => 'The bug I reported has been fixed', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
        ])->save();

        $this->table('permissions')->insert([
            [
                'ID' => 2,
                'Level' => 100,
                'Name' => 'User',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_album_votes' => 1,
                    'site_edit_wiki' => 1,
                    'torrents_add_artist' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 3,
                'Level' => 150,
                'Name' => 'Member',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'zip_downloader' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 4,
                'Level' => 200,
                'Name' => 'Power User',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'forums_polls_create' => 1,
                    'zip_downloader' => 1,
                    'torrents_add_artist' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 5,
                'Level' => 250,
                'Name' => 'Elite',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'forums_polls_create' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'torrents_edit' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 42,
                'Level' => 205,
                'Name' => 'Donor',
                'Values' => serialize([
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'forums_polls_create' => 1,
                    'zip_downloader' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '10',
                'Secondary' => 1
            ],
            [
                'ID' => 23,
                'Level' => 255,
                'Name' => 'First Line Support',
                'Values' => serialize([
                    'site_collages_personal' => 1,
                    'site_advanced_top10' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '28',
                'Secondary' => 1
            ],
            [
                'ID' => 41,
                'Level' => 257,
                'Name' => 'Recruiter',
                'Values' => serialize([
                    'site_send_unlimited_invites' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 30,
                'Level' => 300,
                'Name' => 'Interviewer',
                'Values' => serialize([
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '30',
                'Secondary' => 1
            ],
            [
                'ID' => 31,
                'Level' => 310,
                'Name' => 'Torrent Celebrity',
                'Values' => serialize([
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 32,
                'Level' => 320,
                'Name' => 'Designer',
                'Values' => serialize([
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'forums_polls_create' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '33',
                'Secondary' => 1
            ],
            [
                'ID' => 33,
                'Level' => 330,
                'Name' => 'Security Team',
                'Values' => serialize([
                    'site_send_unlimited_invites' => 1,
                    'forums_polls_create' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 34,
                'Level' => 340,
                'Name' => 'IRC Team',
                'Values' => serialize([
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 35,
                'Level' => 350,
                'Name' => 'Shadow Team',
                'Values' => serialize([
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_advanced_top10' => 1,
                    'site_can_invite_always' => 1,
                    'site_send_unlimited_invites' => 1,
                    'site_disable_ip_history' => 1,
                    'users_edit_profiles' => 1,
                    'users_view_friends' => 1,
                    'users_disable_users' => 1,
                    'users_disable_posts' => 1,
                    'users_disable_any' => 1,
                    'users_view_invites' => 1,
                    'users_view_email' => 1,
                    'users_mod' => 1,
                    'admin_advanced_user_search' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 36,
                'Level' => 360,
                'Name' => 'Alpha Team',
                'Values' => serialize([
                    'admin_reports' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 37,
                'Level' => 370,
                'Name' => 'Bravo Team',
                'Values' => serialize([
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 1
            ],
            [
                'ID' => 38,
                'Level' => 380,
                'Name' => 'Charlie Team',
                'Values' => serialize([
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_moderate_requests' => 1,
                    'site_delete_artist' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'site_tag_aliases_read' => 1,
                    'torrents_edit' => 1,
                    'torrents_delete' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '31',
                'Secondary' => 1
            ],
            [
                'ID' => 39,
                'Level' => 395,
                'Name' => 'Delta Team',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_can_invite_always' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '35',
                'Secondary' => 1
            ],
            [
                'ID' => 25,
                'Level' => 400,
                'Name' => 'Torrent Master',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'forums_polls_create' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'torrents_edit' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 29,
                'Level' => 450,
                'Name' => 'Power TM',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'forums_polls_create' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'torrents_edit' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 28,
                'Level' => 500,
                'Name' => 'Elite TM',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_send_unlimited_invites' => 1,
                    'forums_polls_create' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'torrents_edit' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 26,
                'Level' => 601,
                'Name' => 'VIP',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_send_unlimited_invites' => 1,
                    'forums_polls_create' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                    'torrents_edit' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 27,
                'Level' => 605,
                'Name' => 'Legend',
                'Values' => serialize([
                ]),
                'DisplayStaff' => '0',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 21,
                'Level' => 820,
                'Name' => 'Forum Moderator',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_send_unlimited_invites' => 1,
                    'forums_polls_create' => 1,
                    'site_moderate_forums' => 1,
                    'site_admin_forums' => 1,
                    'site_delete_tag' => 1,
                    'site_disable_ip_history' => 1,
                    'zip_downloader' => 1,
                    'site_proxy_images' => 1,
                    'site_search_many' => 1,
                    'site_tag_aliases_read' => 1,
                    'users_edit_titles' => 1,
                    'users_edit_avatars' => 1,
                    'users_warn' => 1,
                    'users_disable_posts' => 1,
                    'users_override_paranoia' => 1,
                    'torrents_edit' => 1,
                    'torrents_delete' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                    'admin_reports' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 11,
                'Level' => 900,
                'Name' => 'Moderator',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_delete' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_send_unlimited_invites' => 1,
                    'site_moderate_requests' => 1,
                    'site_delete_artist' => 1,
                    'forums_polls_create' => 1,
                    'site_moderate_forums' => 1,
                    'site_admin_forums' => 1,
                    'site_view_torrent_snatchlist' => 1,
                    'site_delete_tag' => 1,
                    'site_disable_ip_history' => 1,
                    'zip_downloader' => 1,
                    'site_proxy_images' => 1,
                    'site_search_many' => 1,
                    'site_tag_aliases_read' => 1,
                    'users_edit_titles' => 1,
                    'users_edit_avatars' => 1,
                    'users_edit_invites' => 1,
                    'users_edit_reset_keys' => 1,
                    'users_view_friends' => 1,
                    'users_warn' => 1,
                    'users_disable_users' => 1,
                    'users_disable_posts' => 1,
                    'users_disable_any' => 1,
                    'users_view_invites' => 1,
                    'users_view_seedleech' => 1,
                    'users_view_uploaded' => 1,
                    'users_view_keys' => 1,
                    'users_view_ips' => 1,
                    'users_view_email' => 1,
                    'users_invite_notes' => 1,
                    'users_override_paranoia' => 1,
                    'users_logout' => 1,
                    'users_mod' => 1,
                    'torrents_edit' => 1,
                    'torrents_delete' => 1,
                    'torrents_delete_fast' => 1,
                    'torrents_freeleech' => 1,
                    'torrents_search_fast' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                    'admin_manage_fls' => 1,
                    'admin_reports' => 1,
                    'admin_advanced_user_search' => 1,
                    'admin_clear_cache' => 1,
                    'admin_whitelist' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 24,
                'Level' => 800,
                'Name' => 'Developer',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_can_invite_always' => 1,
                    'site_send_unlimited_invites' => 1,
                    'forums_polls_create' => 1,
                    'site_view_flow' => 1,
                    'site_view_full_log' => 1,
                    'site_view_torrent_snatchlist' => 1,
                    'site_delete_tag' => 1,
                    'zip_downloader' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '35',
                'Secondary' => 0
            ],
            [
                'ID' => 40,
                'Level' => 980,
                'Name' => 'Administrator',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_delete' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_can_invite_always' => 1,
                    'site_send_unlimited_invites' => 1,
                    'site_moderate_requests' => 1,
                    'site_delete_artist' => 1,
                    'forums_polls_create' => 1,
                    'forums_polls_moderate' => 1,
                    'site_moderate_forums' => 1,
                    'site_admin_forums' => 1,
                    'site_view_flow' => 1,
                    'site_view_full_log' => 1,
                    'site_view_torrent_snatchlist' => 1,
                    'site_delete_tag' => 1,
                    'site_disable_ip_history' => 1,
                    'zip_downloader' => 1,
                    'site_proxy_images' => 1,
                    'site_search_many' => 1,
                    'site_collages_recover' => 1,
                    'site_tag_aliases_read' => 1,
                    'users_edit_ratio' => 1,
                    'users_edit_titles' => 1,
                    'users_edit_avatars' => 1,
                    'users_edit_invites' => 1,
                    'users_edit_watch_hours' => 1,
                    'users_edit_reset_keys' => 1,
                    'users_edit_profiles' => 1,
                    'users_view_friends' => 1,
                    'users_reset_own_keys' => 1,
                    'users_edit_password' => 1,
                    'users_promote_below' => 1,
                    'users_warn' => 1,
                    'users_disable_users' => 1,
                    'users_disable_posts' => 1,
                    'users_disable_any' => 1,
                    'users_delete_users' => 1,
                    'users_view_invites' => 1,
                    'users_view_seedleech' => 1,
                    'users_view_uploaded' => 1,
                    'users_view_keys' => 1,
                    'users_view_ips' => 1,
                    'users_view_email' => 1,
                    'users_invite_notes' => 1,
                    'users_override_paranoia' => 1,
                    'users_logout' => 1,
                    'users_mod' => 1,
                    'torrents_edit' => 1,
                    'torrents_delete' => 1,
                    'torrents_delete_fast' => 1,
                    'torrents_freeleech' => 1,
                    'torrents_search_fast' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                    'torrents_edit_vanityhouse' => 1,
                    'artist_edit_vanityhouse' => 1,
                    'admin_manage_blog' => 1,
                    'admin_manage_fls' => 1,
                    'admin_reports' => 1,
                    'admin_advanced_user_search' => 1,
                    'admin_manage_ipbans' => 1,
                    'admin_dnu' => 1,
                    'admin_clear_cache' => 1,
                    'admin_whitelist' => 1,
                    'admin_manage_wiki' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
            [
                'ID' => 15,
                'Level' => 1000,
                'Name' => 'Sysop',
                'Values' => serialize([
                    'site_leech' => 1,
                    'site_upload' => 1,
                    'site_vote' => 1,
                    'site_submit_requests' => 1,
                    'site_advanced_search' => 1,
                    'site_top10' => 1,
                    'site_advanced_top10' => 1,
                    'site_album_votes' => 1,
                    'site_torrents_notify' => 1,
                    'site_collages_create' => 1,
                    'site_collages_manage' => 1,
                    'site_collages_delete' => 1,
                    'site_collages_subscribe' => 1,
                    'site_collages_personal' => 1,
                    'site_collages_renamepersonal' => 1,
                    'site_make_bookmarks' => 1,
                    'site_edit_wiki' => 1,
                    'site_can_invite_always' => 1,
                    'site_send_unlimited_invites' => 1,
                    'site_moderate_requests' => 1,
                    'site_delete_artist' => 1,
                    'site_moderate_forums' => 1,
                    'site_admin_forums' => 1,
                    'site_view_flow' => 1,
                    'site_view_full_log' => 1,
                    'site_view_torrent_snatchlist' => 1,
                    'site_delete_tag' => 1,
                    'site_disable_ip_history' => 1,
                    'zip_downloader' => 1,
                    'site_debug' => 1,
                    'site_proxy_images' => 1,
                    'site_search_many' => 1,
                    'users_edit_usernames' => 1,
                    'users_edit_ratio' => 1,
                    'users_edit_own_ratio' => 1,
                    'users_edit_titles' => 1,
                    'users_edit_avatars' => 1,
                    'users_edit_invites' => 1,
                    'users_edit_watch_hours' => 1,
                    'users_edit_reset_keys' => 1,
                    'users_edit_profiles' => 1,
                    'users_view_friends' => 1,
                    'users_reset_own_keys' => 1,
                    'users_edit_password' => 1,
                    'users_promote_below' => 1,
                    'users_promote_to' => 1,
                    'users_give_donor' => 1,
                    'users_warn' => 1,
                    'users_disable_users' => 1,
                    'users_disable_posts' => 1,
                    'users_disable_any' => 1,
                    'users_delete_users' => 1,
                    'users_view_invites' => 1,
                    'users_view_seedleech' => 1,
                    'users_view_uploaded' => 1,
                    'users_view_keys' => 1,
                    'users_view_ips' => 1,
                    'users_view_email' => 1,
                    'users_invite_notes' => 1,
                    'users_override_paranoia' => 1,
                    'users_logout' => 1,
                    'users_make_invisible' => 1,
                    'users_mod' => 1,
                    'torrents_edit' => 1,
                    'torrents_delete' => 1,
                    'torrents_delete_fast' => 1,
                    'torrents_freeleech' => 1,
                    'torrents_search_fast' => 1,
                    'torrents_hide_dnu' => 1,
                    'admin_manage_news' => 1,
                    'admin_manage_blog' => 1,
                    'admin_manage_polls' => 1,
                    'admin_manage_forums' => 1,
                    'admin_manage_fls' => 1,
                    'admin_reports' => 1,
                    'admin_advanced_user_search' => 1,
                    'admin_create_users' => 1,
                    'admin_donor_log' => 1,
                    'admin_manage_ipbans' => 1,
                    'admin_dnu' => 1,
                    'admin_clear_cache' => 1,
                    'admin_whitelist' => 1,
                    'admin_manage_permissions' => 1,
                    'admin_schedule' => 1,
                    'admin_login_watch' => 1,
                    'admin_manage_wiki' => 1,
                    'admin_update_geoip' => 1,
                    'site_collages_recover' => 1,
                    'torrents_add_artist' => 1,
                    'edit_unknowns' => 1,
                    'forums_polls_create' => 1,
                    'forums_polls_moderate' => 1,
                    'torrents_edit_vanityhouse' => 1,
                    'artist_edit_vanityhouse' => 1,
                    'site_tag_aliases_read' => 1,
                ]),
                'DisplayStaff' => '1',
                'PermittedForums' => '',
                'Secondary' => 0
            ],
        ])->save();

        $this->table('wiki_articles')->insert([
          ['Title' => 'Wiki', 'Body' => 'Welcome to your new wiki! Hope this works.', 'MinClassRead' => 100, 'MinClassEdit' => 475, 'Date' => date('Y-m-d H:i:s'), 'Author' => 1]
        ])->save();
        $this->table('wiki_aliases')->insert([['Alias' => 'wiki', 'UserID' => 1, 'ArticleID' => 1]])->save();

        $this->table('stylesheets')->insert([
            ['Name' => 'Layer cake', 'Description' => 'Grey stylesheet by Emm'],
            ['Name' => 'Proton', 'Description' => 'Proton by Protiek'],
            ['Name' => 'postmod', 'Description' => 'Upgrade by anorex'],
            ['Name' => 'Hydro', 'Description' => 'Hydro'],
            ['Name' => 'Kuro', 'Description' => 'Kuro'],
            ['Name' => 'Anorex', 'Description' => 'Anorex'],
            ['Name' => 'Mono', 'Description' => 'Mono'],
            ['Name' => 'Shiro', 'Description' => 'Shiro'],
            ['Name' => 'Minimal', 'Description' => 'Minimal'],
            ['Name' => 'Whatlove', 'Description' => 'Whatlove'],
            ['Name' => 'White.cd', 'Description' => 'White.cd'],
            ['Name' => 'GTFO Spaceship', 'Description' => 'gtfo spaceship'],
            ['Name' => 'Dark Ambient', 'Description' => 'dark ambient'],
            ['Name' => 'Xanax cake', 'Description' => 'Xanax cake'],
            ['Name' => 'Haze', 'Description' => 'Haze by Exanurous & apopagasm'],
            ['Name' => 'Post Office', 'Description' => 'Post Office by dannymichel'],
            ['Name' => 'LinoHaze', 'Description' => 'LinoHaze by linotype'],
            ['Name' => 'ApolloStage', 'Description' => 'ApolloStage by burtoo', 'Default' => '1'],
            ['Name' => 'ApolloStage Coffee', 'Description' => 'ApolloStage by burtoo'],
            ['Name' => 'ApolloStage Sunset', 'Description' => 'ApolloStage Sunset by burtoo'],
            ['Name' => 'Apollo Mat', 'Description' => 'Apollo Mat by salem']
        ])->save();

        $this->table('schedule')->insert([
          ['NextHour' => 0, 'NextDay' => 0, 'NextBiWeekly' => 0]
        ])->save();
    }
}
