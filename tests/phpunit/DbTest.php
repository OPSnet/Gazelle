<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;

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
            ", SQLDB
        );

        $dbMan = new DB();
        foreach ($db->collect(0, false) as $tableName) {
            [$ok, $message] = $dbMan->checkStructureMatch(SQLDB, $tableName, "deleted_$tableName");
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
            ", SQLDB, '(?<!_has)_attr$'
        );
        $mysqlAttrTableList = $db->collect(0, false);

        $pgAttrTableList = $this->pg()->column("
            select table_name
            from information_schema.tables
            where table_schema = ?
                and table_name ~ ?
            order by 1
            ", 'public', '(?<!_has)_attr$'
        );

        // Do we have the right number of tables?
        $this->assertCount(5, $mysqlAttrTableList, 'db-mysql-attr-table-total');
        $this->assertCount(5, $pgAttrTableList, 'db-pg-attr-table-total');

        // Are the tables the same?
        $this->assertEquals(
            [],
            array_diff($mysqlAttrTableList, $pgAttrTableList),
            'db-mysql-has-pg-attr-tables'
        );
        $this->assertEquals(
            [],
            array_diff($pgAttrTableList, $mysqlAttrTableList),
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
            $mysql = $db->to_array(false, MYSQLI_ASSOC, false);
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
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate((new DB())->now()), 'db-current-date');
    }

    public function testDbVersion(): void {
        // to check the executability of the SQL inside
        $this->assertIsString((new DB())->version(), 'db-version');
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
    }

    public function testGlobalStatus(): void {
        $status = (new DB())->globalStatus();
        $this->assertGreaterThan(500, count($status), 'db-global-status');
        $this->assertEquals('server-cert.pem', $status['Current_tls_cert']['Value'], 'db-current-tls-cert');
    }

    public function testGlobalVariables(): void {
        $list = (new DB())->globalVariables();
        $this->assertGreaterThan(500, count($list), 'db-global-variables');
        $this->assertEquals('ON', $list['foreign_key_checks']['Value'], 'db-foreign-key-checks-on');
    }

    public function testLongRunning(): void {
        $this->assertEquals(0, (new DB())->longRunning(), 'db-long-running');
    }

    public function testPgBasic(): void {
        $this->assertInstanceOf(\PDO::class, $this->pg()->pdo(), 'db-pg-pdo');
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
        $this->assertEquals(4, $this->pg()->insert("
            insert into t (label) values (?), (?), (?)
            ", 'abc', 'def', 'ghi'),
            'db-pg-triple-insert'
        );
        $this->assertEquals([2], $this->pg()->row("
            select id_t from t where label = ?
            ", 'abc'),
            'db-pg-row'
        );
        $this->assertEquals(
            ['i' => 3, 'j' => 'def'],
            $this->pg()->rowAssoc("select id_t as i, label as j from t where id_t = ?", 3),
            'db-pg-assoc-row'
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

    public function testPgStats(): void {
        $this->pg()->stats()->flush();
        $this->pg()->prepared_query('select now()');
        usleep(1000);
        $this->pg()->prepared_query('select now()');
        $this->pg()->prepared_query("
            select now() + ? * '1 day'::interval
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
            "select now() + ? * '1 day'::interval",
            trim($last['query']),
            'pg-stats-query',
        );
        $this->assertEquals([3], $last['args'], 'pg-stats-args');
        $this->assertEquals(1, $last['metric'], 'pg-stats-metric');

        $this->pg()->stats()->error('computer says no');
        $errorList = $this->pg()->stats()->errorList();
        $this->assertCount(1, $errorList, 'db-pg-error-count');
        $this->assertEquals('computer says no', $errorList[0]['query'], 'db-pg-error-query');
        $this->assertArrayHasKey('epoch', $errorList[0], 'db-pg-error-epoch');
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
}
