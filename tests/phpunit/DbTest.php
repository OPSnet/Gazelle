<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class DbTest extends TestCase {
    public function testTableCoherency() {
        $db = Gazelle\DB::DB();
        $db->prepared_query($sql = "
             SELECT replace(table_name, 'deleted_', '') as table_name
             FROM information_schema.tables
             WHERE table_schema = ?
                AND table_name LIKE 'deleted_%'
            ", SQLDB
        );

        $dbMan = new Gazelle\DB;
        foreach ($db->collect(0, false) as $tableName) {
            [$ok, $message] = $dbMan->checkStructureMatch(SQLDB, $tableName, "deleted_$tableName");
            $this->assertTrue($ok, "mismatch -- $message");
        }
    }

    public function testGlobalStatus() {
        $status = (new Gazelle\DB)->globalStatus();
        $this->assertGreaterThan(500, count($status), 'db-global-status');
        $this->assertEquals('server-cert.pem', $status['Current_tls_cert']['Value'], 'db-current-tls-cert');
    }

    public function testGlobalVariables() {
        $list = (new Gazelle\DB)->globalVariables();
        $this->assertGreaterThan(500, count($list), 'db-global-variables');
        $this->assertEquals('ON', $list['foreign_key_checks']['Value'], 'db-foreign-key-checks-on');
    }

    public function testLongRunning() {
        $this->assertEquals(0, (new Gazelle\DB)->longRunning(), 'db-long-running');
    }
}
