<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class PgInfoTest extends TestCase {
    use Pg;

    public function testPgInfoList(): void {
        $pgInfo = new DB\PgInfo(
            Enum\PgInfoOrderBy::tableName,
            Enum\Direction::descending,
        );
        $list = $pgInfo->info();
        $this->assertEquals(
            'userclass_has_privilege',
            array_first($list)['table_name'],
            'pginfo-list',
        );
    }

    public function testPgInfoColumn(): void {
        $this->assertCount(10, DB\PgInfo::columnList(), 'pginfo-column-list');
    }

    public function testCheckpointInfo(): void {
        $info = $this->pg()->checkpointInfo();
        $this->assertCount(3, $info);
        $this->assertIsInt($info['num_timed'], 'pg-checkpoint-timed');
        $this->assertIsInt($info['num_requested'], 'pg-checkpoint-req');
        $this->assertIsFloat($info['percent'], 'pg-checkpoint-percent');
    }

    public function testPgVersion(): void {
        $this->assertStringStartsWith(
            "PostgreSQL ",
            $this->pg()->version(),
            'pg-version'
        );
    }
}
