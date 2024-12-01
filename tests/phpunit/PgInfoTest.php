<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;
use Gazelle\Enum\PgInfoOrderBy;

class PgInfoTest extends TestCase {
    use Pg;

    public function testDirection(): void {
        $this->assertEquals(
            PgInfoOrderBy::tableSize,
            DB\PgInfo::lookupOrderby('table_size'),
            'pginfo-orderby-tablesize'
        );
        $this->assertEquals(
            PgInfoOrderBy::tableName,
            DB\PgInfo::lookupOrderby('table_name'),
            'pginfo-orderby-tablename'
        );
        $this->assertEquals(
            PgInfoOrderBy::tableName,
            DB\PgInfo::lookupOrderby('wut'),
            'pginfo-orderby-default'
        );
    }

    public function testPgInfoList(): void {
        $pgInfo = new DB\PgInfo(
            PgInfoOrderBy::tableName,
            Direction::descending,
        );
        $list = $pgInfo->info();
        $this->assertEquals('public.user_warning', $list[0]['table_name'], 'pginfo-list');
    }

    public function testCheckpointInfo(): void {
        $info = $this->pg()->checkpointInfo();
        $this->assertCount(3, $info);
        $this->assertIsInt($info['num_timed'], 'pg-checkpoint-timed');
        $this->assertIsInt($info['num_requested'], 'pg-checkpoint-req');
        $this->assertIsFloat($info['percent'], 'pg-checkpoint-percent');
    }

    public function testPgVersion(): void {
        $this->assertIsString($this->pg()->version(), 'pg-version');
    }
}
