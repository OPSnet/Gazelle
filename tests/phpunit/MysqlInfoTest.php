<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;
use Gazelle\Enum\MysqlInfoOrderBy;
use Gazelle\Enum\MysqlTableMode;

class MysqlInfoTest extends TestCase {
    public function testDirection(): void {
        $this->assertEquals(MysqlInfoOrderBy::totalLength, Gazelle\DB\MysqlInfo::lookupOrderby('total_length'), 'mysqlfo-orderby-totallength');
        $this->assertEquals(MysqlInfoOrderBy::tableName, Gazelle\DB\MysqlInfo::lookupOrderby('table_name'), 'mysqlfo-orderby-tablename');
        $this->assertEquals(MysqlInfoOrderBy::tableName, Gazelle\DB\MysqlInfo::lookupOrderby('wut'), 'mysqlfo-orderby-default');
    }

    public function testMysqlInfoList(): void {
        $mysqlInfo = new Gazelle\DB\MysqlInfo(
            MysqlTableMode::all,
            MysqlInfoOrderBy::tableName,
            Direction::descending,
        );
        $list = $mysqlInfo->info();
        $this->assertEquals('xbt_snatched', current($list)['table_name'], 'mysqlinfo-list');
    }
}
