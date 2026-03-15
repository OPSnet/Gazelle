<?php

namespace Gazelle;

use sad_spirit\pg_wrapper\exceptions\server\ProgrammingException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use GazelleUnitTest\Helper;

class DbTest extends TestCase {
    use Pg;

    public function testDirection(): void {
        $this->assertEquals('asc',  DB::lookupDirection('asc')->value, 'db-direction-asc');
        $this->assertEquals('desc', DB::lookupDirection('desc')->value, 'db-direction-desc');
        $this->assertEquals('asc',  DB::lookupDirection('wut')->value, 'db-direction-default');
    }

    public function testTableCoherency(): void {
        $db = DB::DB();
        $db->prepared_query("
             SELECT replace(table_name, 'deleted_', '') as table_name
             FROM information_schema.tables
             WHERE table_schema = ?
                AND table_name LIKE 'deleted_%'
            ", MYSQL_DB
        );

        $dbMan = new DB();
        foreach ($db->collect(0) as $tableName) {
            [$ok, $message] = $dbMan->checkStructureMatch(MYSQL_DB, $tableName, "deleted_$tableName");
            $this->assertTrue($ok, "mismatch -- $message");
        }
    }

    public function testAttrCoherency(): void {
        $db = DB::DB();
        $db->prepared_query("
            select table_name
            from information_schema.tables
            where table_schema = ?
                and table_name regexp ?
            order by 1
            ", MYSQL_DB, '(?<!_has)_attr$'
        );
        $mysqlAttrTableList = $db->collect(0);

        $pgAttrTableList = $this->pg()->column("
            select table_name
            from information_schema.tables
            where table_schema = ?
                and table_name ~ ?
            order by 1
            ", 'public', '(?<!_has)_attr$'
        );

        // Do we have the right number of tables?
        $this->assertCount(6, $mysqlAttrTableList, 'db-mysql-attr-table-total');
        $this->assertCount(5, $pgAttrTableList, 'db-pg-attr-table-total');

        // Are the tables the same?
        $this->assertEquals(
            // during migration
            [4 => 'torrent_group_attr'],
            array_diff($mysqlAttrTableList, $pgAttrTableList),
            'db-mysql-has-pg-attr-tables'
        );
        $this->assertEquals(
            [],
            array_values(array_diff($pgAttrTableList, $mysqlAttrTableList)),
            'db-pg-has-mysql-attr-tables'
        );

        // For each table, are the id and name values identical?
        foreach ($pgAttrTableList as $table) {
            $sql = in_array($table, ['artist_attr'])
                ? "
                    select {$table}_id as id, Name as name
                    from $table
                    order by 1
                " : "
                    select ID as id, Name as name
                    from $table
                    order by 1
                ";
            $db->prepared_query($sql);
            $mysql = $db->to_array(false, MYSQLI_ASSOC);
            $pg    = $this->pg()->all("
                select id_$table as id, name
                from $table
                order by 1
            ");

            $this->assertEquals(
                [],
                array_diff(
                    array_map(fn ($t) => $t['id'], $mysql),
                    array_map(fn ($t) => $t['id'], $pg),
                ),
                "db-attr-identical-id-$table",
            );
            $this->assertEquals(
                [],
                array_diff(
                    array_map(fn ($t) => $t['name'], $mysql),
                    array_map(fn ($t) => $t['name'], $pg),
                ),
                "db-attr-identical-name-$table",
            );
        }
    }

    public function testDbTime(): void {
        $this->assertTrue(Helper::recentDate(new DB()->now()), 'db-current-date');
    }

    public function testDbVersion(): void {
        // to check the executability of the SQL inside
        $this->assertStringStartsWith('8.', new DB()->version(), 'db-version');
    }

    public function testDebug(): void {
        $db = DB::DB();
        $initial = count($db->queryList());
        $tableName = "phpunit_" . randomString(10);
        $db->prepared_query("create temporary table if not exists $tableName (test int)");
        $db->prepared_query("create temporary table if not exists $tableName (test int)");
        $this->assertEquals(1, $db->loadPreviousWarning(), 'db-load-warning');
        $this->assertGreaterThan(0.0, $db->elapsed(), 'db-elapsed');

        $queryList = $db->queryList();
        $this->assertEquals($initial + 2, count($queryList), 'db-querylist');
        $last = end($queryList);
        $this->assertIsArray($last['warning'], 'db-has-warning');
        $warning = $last['warning'];
        $this->assertCount(1, $warning, 'db-warning');
        $this->assertEquals(1050, $warning[0]['code'], 'db-error-code');
        $this->assertEquals("Table '$tableName' already exists", $warning[0]['message'], 'db-error-message');

        $db->disableQueryLog();
        $db->prepared_query('select now()');
        $n = count($queryList);
        $this->assertEquals($n, count($db->queryList()), 'db-query-log-off');
        $db->enableQueryLog();
        $db->prepared_query('select now()');
        $this->assertEquals($n + 1, count($db->queryList()), 'db-query-log-on');
    }

    public function testGlobalStatus(): void {
        $status = new DB()->globalStatus();
        $this->assertGreaterThan(500, count($status), 'db-global-status');
        $this->assertEquals('server-cert.pem', $status['Current_tls_cert']['Value'], 'db-current-tls-cert');
    }

    public function testGlobalVariables(): void {
        $list = new DB()->globalVariables();
        $this->assertGreaterThan(500, count($list), 'db-global-variables');
        $this->assertEquals('ON', $list['foreign_key_checks']['Value'], 'db-foreign-key-checks-on');
    }

    public function testLongRunning(): void {
        $this->assertEquals(0, new DB()->longRunning(), 'db-long-running');
    }

    public function testIndexLists(): void {
        $tableName = 'phpunit_' . randomString(10);
        $dbh = DB::DB();
        $dbh->prepared_query("
            CREATE TABLE $tableName (
                phpunit_id int PRIMARY KEY,
                t1 int,
                t2 int,
                key (t1, t2),
                key (t1)
            )
        ");
        $db = new DB();

        $list = array_filter($db->redundantIndexList(), fn ($t) => $t['table_name'] === $tableName);
        $this->assertCount(1, $list, 'db-index-redundant');
        $redundant = current($list);
        $this->assertEquals($tableName, $redundant['table_name'], 'redundant-table-name');
        $this->assertEquals('t1 (t1,t2)', $redundant['covering_index'], 'covering-index-name');
        $this->assertEquals('t1_2 (t1)', $redundant['redundant_index'], 'redundant-index-name');
        $this->assertEquals(0, $redundant['redundant_read'], 'redundant-redundant-read');
        $this->assertEquals(0, $redundant['covering_read'], 'redundant-covering-read');

        $list = array_filter($db->unusedIndexList(), fn ($t) => $t['table_name'] === $tableName);
        $this->assertCount(2, $list, 'db-index-unused');
        $unused = current($list);
        $this->assertEquals($tableName, $unused['table_name'], 'unused-table-name');
        $this->assertEquals('t1', $unused['index_name'], 'unused-index-name');
        $this->assertEquals('t1 {0}, t2 {0}', $unused['column_list'], 'unused-column-list');
        $dbh->prepared_query("
            DROP TABLE $tableName
        ");
    }

    public function testPgBasic(): void {
        $num = random_int(100, 999);
        $this->assertEquals($num, $this->pg()->scalar("select ?", $num), 'db-pg-scalar-int');
        $this->assertEquals(true, $this->pg()->scalar("select ?", true), 'db-pg-scalar-true');
        $this->assertEquals("test", $this->pg()->scalar("select ?", "test"), 'db-pg-scalar-string');
        $this->assertEquals("test", $this->pg()->scalar("select '\\x74657374'::bytea"), 'db-pg-scalar-bytea');

        $st = $this->pg()->prepare("
            create temporary table t (
                id_t integer not null primary key generated always as identity,
                label text not null,
                created timestamptz not null default current_date
            )
        ");
        $this->assertInstanceOf(\PDOStatement::class, $st, 'db-pg-st');
        $this->assertTrue($st->execute(), 'db-pg-create-tmp-table');

        $id = $this->pg()->insert("
            insert into t (label) values (?)
            ", 'phpunit'
        );
        $this->assertEquals(1, $id, 'db-pg-last-id');
        $this->assertEquals(
            ['phpunit'],
            $this->pg()->row(
                'select label from t where id_t = ?', $id
            ),
            'db-pg-row-assoc-one'
        );
        $this->assertEquals(
            ['label' => 'phpunit'],
            $this->pg()->rowAssoc(
                'select label from t where id_t = ?', $id
            ),
            'db-pg-row-assoc-one'
        );
        $this->assertEquals(
            [],
            $this->pg()->row(
                'select label from t where id_t = ?', $id + 1
            ),
            'db-pg-row-assoc-none'
        );
        $this->assertEquals(
            [],
            $this->pg()->rowAssoc(
                'select label from t where id_t = ?', $id + 1
            ),
            'db-pg-row-assoc-none'
        );
        $this->assertEquals(4, $this->pg()->insert("
            insert into t (label) values (?), (?), (?)
            ", 'abc', 'def', 'ghi'),
            'db-pg-triple-insert'
        );
        $this->assertEquals(
            ['phpunit', 'abc', 'def', 'ghi'],
            $this->pg()->column("select label from t order by id_t"),
            'db-pg-column'
        );
        $all = $this->pg()->all("
            select id_t, label, created from t order by id_t desc
        ");
        $this->assertCount(4, $all, 'pg-all-total');
        $this->assertEquals(['id_t', 'label', 'created'], array_keys($all[0]), 'pg-all-column-names');
        $this->assertEquals('ghi', $all[0]['label'], 'pg-all-row-value');
        $this->assertEquals(
            3,
            $this->pg()->prepared_query("
                update t set label = upper(label) where char_length(label) = ?
                ", 3
            ),
            'db-pg-prepared-update'
        );
    }

    public function testPgBasicex(): void {
        $num = random_int(100, 999);
        $this->assertEquals($num, $this->pg()->scalarex("select $1", $num), 'db-pg-scalar-int');
        $this->assertEquals(true, $this->pg()->scalarex("select $1", true), 'db-pg-scalar-true');
        $this->assertEquals("test", $this->pg()->scalarex("select $1", "test"), 'db-pg-scalar-string');
        $this->assertEquals("test", $this->pg()->scalarex("select '\\x74657374'::bytea"), 'db-pg-scalar-bytea');

        $st = $this->pg()->prepareex("
            create temporary table t (
                id_t integer not null primary key generated always as identity,
                label text not null,
                created timestamptz not null default current_date
            )
        ");
        $st->execute();

        $id = $this->pg()->scalarex("
            insert into t (label) values ($1) returning id_t
            ", 'phpunit'
        );
        $this->assertEquals(1, $id, 'db-pg-last-id');
        $this->assertEquals(
            ['phpunit'],
            $this->pg()->rowex(
                'select label from t where id_t = $1', $id
            ),
            'db-pg-row-assocex-one'
        );
        $this->assertEquals(
            ['label' => 'phpunit'],
            $this->pg()->rowAssocex(
                'select label from t where id_t = $1', $id
            ),
            'db-pg-row-assocex-one'
        );
        $this->assertNull(
            $this->pg()->rowex(
                'select label from t where id_t = $1', $id + 1
            ),
            'db-pg-row-assocex-none'
        );
        $this->assertNull(
            $this->pg()->rowAssocex(
                'select label from t where id_t = $1', $id + 1
            ),
            'db-pg-row-assocex-none'
        );
        $this->assertEquals([2, 3, 4], $this->pg()->columnex("
            insert into t (label) values ($1), ($2), ($3) returning id_t
            ", 'abc', 'def', 'ghi'),
            'db-pg-triple-insert'
        );
        $this->assertEquals(
            ['phpunit', 'abc', 'def', 'ghi'],
            $this->pg()->columnex("select label from t order by id_t"),
            'db-pg-column'
        );
        $this->assertEquals(
            [],
            $this->pg()->allex("
                select id_t, label, created from t where id_t < 0 order by id_t desc
            "),
            'pg-all-none'
        );
        $all = $this->pg()->allex("
            select id_t, label, created from t order by id_t desc
        ");
        $this->assertCount(4, $all, 'pg-all-total');
        $this->assertEquals(['id_t', 'label', 'created'], array_keys($all[0]), 'pg-all-column-names');
        $this->assertEquals('ghi', $all[0]['label'], 'pg-all-row-value');
        $this->assertEquals(
            3,
            $this->pg()->query("
                update t set label = upper(label) where char_length(label) = $1
                ", 3
            ),
            'db-pg-prepared-update'
        );
        $all = $this->pg()->allByKeyex('label', "
            select id_t, label from t where label = 'ABC'
        ");
        $this->assertEquals(['id_t' => 2, 'label' => 'ABC'], $all['ABC'], 'pg-all-column-names-by-key');
    }

    public function testPgStats(): void {
        $this->pg()->stats()->flush();
        $this->pg()->query('select now()');
        usleep(1000);
        $this->pg()->query('select now()');
        $this->pg()->query("
            select now() + $1 * '1 day'::interval
            ", 3
        );

        $queryList = $this->pg()->stats()->queryList();
        $this->assertCount(3, $queryList, 'db-pg-query-count');
        $this->assertGreaterThan(
            $queryList[0]['epoch'],
            $queryList[1]['epoch'],
            'db-pg-stats-epoch'
        );
        $last = end($queryList);
        $this->assertEquals(
            "select now() + $1 * '1 day'::interval",
            trim($last['query']),
            'pg-stats-query',
        );
        $this->assertEquals([3], $last['args'], 'pg-stats-args');
        $this->assertEquals(1, $last['metric'], 'pg-stats-metric');

        $this->pg()->stats()->error('computer says no', [123]);
        $errorList = $this->pg()->stats()->errorList();
        $this->assertCount(1, $errorList, 'db-pg-error-count');
        $this->assertEquals('computer says no', $errorList[0]['query'], 'db-pg-error-query');
        $this->assertArrayHasKey('epoch', $errorList[0], 'db-pg-error-epoch');
        $this->assertEquals([123], $errorList[0]['args'], 'db-pg-error-args');
    }

    public function testPgBadQuery(): void {
        $stats = $this->pg()->stats();
        $queryCnt = count($stats->queryList());
        $errorCnt = count($stats->errorList());

        try {
            $this->pg()->executeParams("select 1 from nonexistent_table");
            $this->fail('pg-bad-exception-not-thrown');
        } catch (ProgrammingException $e) {
            $this->assertEquals(ProgrammingException::class, $e::class, 'pg-bad-exception-class');
        }

        $this->assertEquals($queryCnt, count($stats->queryList()), 'pg-bad-query-count');
        $this->assertEquals($errorCnt + 1, count($stats->errorList()), 'pg-bad-error-count');
    }

    public function testPgAbort(): void {
        // make sure we are not in a tx
        $this->pg()->prepared_query('select 1');
        // abort non-existent tx, should be noop
        $this->pg()->abort();

        $this->pg()->begin();
        $this->assertEquals(1, $this->pg()->scalarex('select 1'), 'pg-abort-qry1');

        try {
            $this->pg()->prepared_query('select 1');
            $this->fail('pg-abort-exception-not-thrown');
        } catch (\Exception $e) {
            $this->assertEquals(\Exception::class, $e::class, 'pg-abort-exception-class');
            $this->assertEquals('ongoing pg_wrapper transaction', $e->getMessage(), 'pg-abort-exception-msg');
        }

        $this->assertEquals(1, $this->pg()->scalarex('select 1'), 'pg-abort-qry2');

        $this->pg()->abort();
        $this->assertEquals(1, $this->pg()->scalar('select 1'), 'pg-abort-qry3');
    }

    public function testMysqlDuplicateException(): void {
        $this->expectException(DB\MysqlDuplicateKeyException::class);
        DB::DB()->prepared_query("
            INSERT INTO users_main
                   (ID, Username, Email, PassHash, torrent_pass, IP, PermissionID, Enabled, Invites, ipcc, auth_key, stylesheet_id)
            VALUES (1,  'phpunit', '',   '',       '',           '', 0,            '0',     0,       '',   '',       0)
        ");
    }

    public function testMysqlMisc(): void {
        $db = DB::DB();
        $this->assertTrue(
            $db->entityExists('users_main', 'Username'),
            'db-mysql-entity-exists'
        );
        $this->assertEquals($db->info(), 'mysql via TCP/IP', 'db-mysql-info');
    }

    public function testMysqlTable(): void {
        $bad = new DB\MysqlTable('nosuchtable');
        $this->assertFalse($bad->exists(), 'mysql-table-does-not-exist');

        $table = new DB\MysqlTable('collages');
        $this->assertTrue($table->exists(), 'mysql-table-exists');
        $this->assertEquals(
            "tools.php?action=db-mysql&table={$table->name}",
            $table->location(),
            'mysql-table-location',
        );
        $this->assertEquals(
            "<a href=\"tools.php?action=db-mysql&amp;table=collages\">collages</a>",
            $table->link(),
            'mysql-table-link',
        );
        $this->assertStringStartsWith(
            'CREATE TABLE `' . $table->name . '` (',
            $table->definition(),
            'mysql-table-definition',
        );

        // if this fails, check the permissions on information_schema.table_statistics
        $this->assertEquals(
            [
                "ROWS_READ", "ROWS_CHANGED", "ROWS_CHANGED_X_INDEXES",
            ],
            array_keys($table->tableRead()),
            'mysql-table-table-read',
        );
        $this->assertEquals(
            [
                "index_name", "rows_read", "column_list",
            ],
            array_keys($table->indexRead()[0]),
            'mysql-table-index-read',
        );
        $this->assertEquals(
            [
                "TABLE_ROWS", "AVG_ROW_LENGTH", "DATA_LENGTH", "INDEX_LENGTH",
                "DATA_FREE", "ROWS_READ", "ROWS_CHANGED", "ROWS_CHANGED_X_INDEXES",
                "iops",
            ],
            array_keys($table->stats()),
            'mysql-table-stats',
        );
        $this->assertEquals(
            [
                'bookmarks_collages',
                'collage_has_attr',
                'collages_artists',
                'collages_torrents',
                'users_collage_subs',
            ],
            array_map(
                fn ($t) => $t['TABLE_NAME'],
                $table->foreignKeyList(),
            ),
            'mysql-fkey-list'
        );
        // not a problem that the table does not exist, the SQL is being tested
        $this->assertFalse($bad->recentUpdate(600), 'mysql-table-recent-update');
    }

    public function testPgTable(): void {
        $bad = new DB\PgTable('nosuchtable');
        $this->assertFalse($bad->exists(), 'pg-table-does-not-exist');

        $table = new DB\PgTable('user_audit_trail');
        $this->assertTrue($table->exists(), 'pg-table-exists');
        $this->assertEquals(
            "tools.php?action=db-pg&table={$table->name}",
            $table->location(),
            'pg-table-location',
        );
        $this->assertEquals(
            "<a href=\"tools.php?action=db-pg&amp;table=user_audit_trail\">user_audit_trail</a>",
            $table->link(),
            'pg-table-link',
        );
        $this->assertStringStartsWith(
            "create table public.{$table->name} (",
            $table->definition(),
            'pg-table-definition',
        );

        $this->assertEquals(
            [
                "seq_scan", "last_seq_scan", "seq_tup_read", "idx_scan",
                "last_idx_scan", "idx_tup_fetch", "n_tup_ins", "n_tup_upd",
                "n_tup_del", "n_tup_hot_upd", "n_tup_newpage_upd",
                "n_live_tup", "n_dead_tup", "n_ins_since_vacuum",
                "last_vacuum", "last_autovacuum", "vacuum_count",
                "autovacuum_count", "n_mod_since_analyze", "analyze_count",
                "autoanalyze_count",
            ],
            array_keys($table->tableRead()),
            'pg-table-table-read',
        );

        $indexRead = $table->indexRead();
        $this->assertCount(4, $indexRead, 'pg-table-index-read-total');
        $index = $indexRead[0];
        $this->assertEquals(
            [
                "indexrelname", "idx_scan", "idx_tup_read",
                "idx_tup_fetch", "last_idx_scan",
            ],
            array_keys($index),
            'pg-table-index-read'
        );

        $this->assertEquals(
            [
                "table_size", "index_size", "live", "dead", "dead_ratio",
                "analyze_delta", "vacuum_delta", "analyze_total",
                "vacuum_total",
            ],
            array_keys($table->stats()),
            'pg-table-stats',
        );
    }

    public function testPgByteaScalar(): void {
        $this->pg()->prepared_query("
            create temporary table test_bytea (
                payload bytea not null primary key
            )
        ");
        $payload = pack('C*', array_map(fn ($n) => chr($n), range(0, 255)));
        $this->assertEquals(
            1,
            $this->pg()->prepared_query("
                insert into test_bytea (payload) values (?)
                ", $payload
            ),
            'db-pg-insert-bytea'
        );
        $this->assertEquals(
            $payload,
            $this->pg()->scalar("select payload from test_bytea"),
            'db-pg-scalar-bytea'
        );
        $this->pg()->prepared_query("
            drop table test_bytea
        ");
    }

    public function testPgByteaScalarex(): void {
        $this->pg()->query("
            create temporary table test_bytea (
                payload bytea not null primary key
            )
        ");
        $payload = pack('C*', array_map(fn ($n) => chr($n), range(0, 255)));
        $this->assertEquals(
            1,
            $this->pg()->query("
                insert into test_bytea (payload) values ($1)
                ", $payload
            ),
            'db-pg-insert-bytea'
        );
        $this->assertEquals(
            $payload,
            $this->pg()->scalarex("select payload from test_bytea"),
            'db-pg-scalar-bytea'
        );
        $this->pg()->query("
            drop table test_bytea
        ");
    }

    public function testPgWriteReturn(): void {
        $this->pg()->prepared_query("
            create temporary table test1 (
                t_id int not null primary key
            )
        ");
        $n = random_int(1000, 9999);
        $value = $this->pg()->writeReturning("
            insert into test1 (t_id) values (?) returning t_id
            ", $n
        );
        $this->assertEquals($n, $value, 'db-pg-write-scalar');

        $this->pg()->prepared_query("
            create temporary table test2 (
                t_id int not null primary key,
                label text not null
            )
        ");
        $n = random_int(1000, 9999);
        $label = randomString();
        $row = $this->pg()->writeReturningRow("
            insert into test2 (t_id, label) values (?, ?) returning t_id, label
            ", $n, $label
        );
        $this->assertEquals([$n, $label], $row, 'db-pg-write-row');
    }

    public function testPgWriteReturnex(): void {
        $this->pg()->query("
            create temporary table test1 (
                t_id int not null primary key
            )
        ");
        $n = random_int(1000, 9999);
        $value = $this->pg()->writeReturningex("
            insert into test1 (t_id) values ($1) returning t_id
            ", $n
        );
        $this->assertEquals($n, $value, 'db-pg-write-scalar');

        $this->pg()->query("
            create temporary table test2 (
                t_id int not null primary key,
                label text not null
            )
        ");
        $n = random_int(1000, 9999);
        $label = randomString();
        $row = $this->pg()->writeReturningRowex("
            insert into test2 (t_id, label) values ($1, $2) returning t_id, label
            ", $n, $label
        );
        $this->assertEquals([$n, $label], $row, 'db-pg-write-row');
    }

    public function testPgByteaAll(): void {
        $this->pg()->prepared_query("
            create temporary table test_bytea (
                id int generated always as identity,
                payload bytea not null
            )
        ");
        $payload = pack('C*', array_map(fn ($n) => chr($n), range(0, 255)));
        $this->pg()->prepared_query("
            insert into test_bytea (payload) values (?), (?)
            ", $payload, $payload
        );
        $this->assertEquals(
            [
                ['id' => 1, 'payload' => $payload],
                ['id' => 2, 'payload' => $payload],
            ],
            $this->pg()->all("select id, payload from test_bytea order by id"),
            'db-pg-all-bytea'
        );
        $this->pg()->prepared_query("
            drop table test_bytea
        ");
    }

    public function testPgByteaAllex(): void {
        $this->pg()->query("
            create temporary table test_bytea (
                id int generated always as identity,
                payload bytea not null
            )
        ");
        $payload = pack('C*', array_map(fn ($n) => chr($n), range(0, 255)));
        $this->pg()->query("
            insert into test_bytea (payload) values ($1), ($2)
            ", $payload, $payload
        );
        $this->assertEquals(
            [
                ['id' => 1, 'payload' => $payload],
                ['id' => 2, 'payload' => $payload],
            ],
            $this->pg()->allex("select id, payload from test_bytea order by id"),
            'db-pg-all-bytea'
        );
        $this->pg()->query("
            drop table test_bytea
        ");
    }

    #[Group('no-ci')]
    public function testMysqlWrite(): void {
        $db = DB::DB(readWrite: false);
        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionMessageMatches(
            "/^INSERT command denied to user '"
            . MYSQL_RO_USER
            . "'@'[^']+' for table 'site_options'$/"
        );
        $db->prepared_query("
            INSERT INTO site_options
                   (Name,      Value,            Comment)
            VALUES ('phpunit', 'testMysqlWrite', 'this shall not pass')
        ");
    }

    public function testMysqlPrepare(): void {
        $db = DB::DB();
        $this->assertInstanceOf(
            \mysqli_stmt::class,
            $db->prepare('select now()'),
            'db-mysql-prepare-ok'
        );
        $this->expectException(\mysqli_sql_exception::class);
        $db->prepare('this is not sql');
    }

    public function testPgWrapper(): void {
        $this->pg()->execute("
            create temporary table phpunit_pg_wrapper (
                t_id int not null primary key generated always as identity,
                num int[],
                word text[]
            );
        ");
        $this->pg()->execute("
            insert into phpunit_pg_wrapper (num, word) values
                ('{2, 4, 6}', '{\"even\"}'),
                ('{2, 3, 5, 7, 11}', '{\"prime\", \"primal\"}'),
                ('{1, 2, 3, 5, 8, 13}', '{\"fib\"}')
        ");
        $result = $this->pg()->executeParams(
            "select word from phpunit_pg_wrapper where $1 = any(num);",
            3
        );
        $this->assertEquals(
            [
                ["word" => ["prime", "primal"]],
                ["word" => ["fib"]],
            ],
            $result->fetchAll(),
            'pg-wrapper-execute'
        );
    }

    public function testPgInsertCopy(): void {
        $this->pg()->prepared_query("
            create temporary table phpunit_insert_copy
            (num int, name text, flag bool)
        ");
        $output = [
            ['num' =>  100, 'name' => "abc\ndef", 'flag' =>  true],
            ['num' => -100, 'name' => "ghi\tjkl", 'flag' => false],
            ['num' =>   17, 'name' =>       null, 'flag' =>     0],
            ['num' => null, 'name' =>       null, 'flag' =>  null],
        ];
        $input = array_map(fn ($r) => array_values($r), $output);
        $this->assertTrue(
            $this->pg()->insertCopy(
                'phpunit_insert_copy',
                ['num', 'name', 'flag'],
                $input
            ),
            'pg-insert-copy',
        );
        $this->assertEquals(
            $output,
            $this->pg()->all("select * from phpunit_insert_copy"),
            'pg-read-insert',
        );
    }

    public function testPgWrite(): void {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches(
            '/^SQLSTATE\[\d+\]: Insufficient privilege: \d+ ERROR:  permission denied for table counter/'
        );
        $this->pgro()->prepared_query("
            insert into counter values ('phpunit-testWrite', 'fail on insert', 0)
        ");
    }
}
