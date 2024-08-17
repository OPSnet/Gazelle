<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;
use Gazelle\Enum\PgInfoOrderBy;

class PgInfoTest extends TestCase {
    use Gazelle\Pg;

    public function testDirection(): void {
        $this->assertEquals(PgInfoOrderBy::tableSize, Gazelle\DB\PgInfo::lookupOrderby('table_size'), 'pginfo-orderby-tablesize');
        $this->assertEquals(PgInfoOrderBy::tableName, Gazelle\DB\PgInfo::lookupOrderby('table_name'), 'pginfo-orderby-tablename');
        $this->assertEquals(PgInfoOrderBy::tableName, Gazelle\DB\PgInfo::lookupOrderby('wut'), 'pginfo-orderby-default');
    }

    public function testPgInfoList(): void {
        $pgInfo = new Gazelle\DB\PgInfo(
            PgInfoOrderBy::tableName,
            Direction::descending,
        );
        $list = $pgInfo->info();
        $this->assertEquals('public.user_warning', $list[0]['table_name'], 'pginfo-list');
    }
}
