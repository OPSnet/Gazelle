<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;

class DbTest extends TestCase {
    use Gazelle\Pg;

    public function testDirection(): void {
        $this->assertEquals('asc',  Gazelle\DB::lookupDirection('asc')->value, 'db-direction-asc');
        $this->assertEquals('desc', Gazelle\DB::lookupDirection('desc')->value, 'db-direction-desc');
        $this->assertEquals('asc',  Gazelle\DB::lookupDirection('wut')->value, 'db-direction-default');
    }

    public function testTableCoherency(): void {
        $db = Gazelle\DB::DB();
        $db->prepared_query($sql = "
             SELECT replace(table_name, 'deleted_', '') as table_name
             FROM information_schema.tables
             WHERE table_schema = ?
                AND table_name LIKE 'deleted_%'
            ", SQLDB
        );

        $dbMan = new Gazelle\DB();
        foreach ($db->collect(0, false) as $tableName) {
            [$ok, $message] = $dbMan->checkStructureMatch(SQLDB, $tableName, "deleted_$tableName");
            $this->assertTrue($ok, "mismatch -- $message");
        }
    }

    public function testDbTime(): void {
        $this->assertTrue(Helper::recentDate((new Gazelle\DB())->now()), 'db-current-date');
    }

    public function testDbVersion(): void {
        // to check the executability of the SQL inside
        $this->assertIsString((new Gazelle\DB())->version(), 'db-version');
    }

    public function testDebug(): void {
        $db = Gazelle\DB::DB();
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
        $status = (new Gazelle\DB())->globalStatus();
        $this->assertGreaterThan(500, count($status), 'db-global-status');
        $this->assertEquals('server-cert.pem', $status['Current_tls_cert']['Value'], 'db-current-tls-cert');
    }

    public function testGlobalVariables(): void {
        $list = (new Gazelle\DB())->globalVariables();
        $this->assertGreaterThan(500, count($list), 'db-global-variables');
        $this->assertEquals('ON', $list['foreign_key_checks']['Value'], 'db-foreign-key-checks-on');
    }

    public function testLongRunning(): void {
        $this->assertEquals(0, (new Gazelle\DB())->longRunning(), 'db-long-running');
    }

    public function testPg(): void {
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
