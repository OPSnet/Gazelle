<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\Direction;
use Gazelle\Enum\MysqlInfoOrderBy;
use Gazelle\Enum\MysqlTableMode;

class MysqlInfoTest extends TestCase {
    public function testDirection(): void {
        $this->assertEquals(
            MysqlInfoOrderBy::totalLength,
            DB\MysqlInfo::lookupOrderby('total_length'),
            'mysqlfo-orderby-totallength',
        );
        $this->assertEquals(
            MysqlInfoOrderBy::tableName,
            DB\MysqlInfo::lookupOrderby('table_name'),
            'mysqlfo-orderby-tablename',
        );
        $this->assertEquals(
            MysqlInfoOrderBy::tableName,
            DB\MysqlInfo::lookupOrderby('wut'),
            'mysqlfo-orderby-default',
        );
    }

    public function testMysqlInfoColumn(): void {
        $this->assertCount(10, DB\MysqlInfo::columnList(), 'myinfo-column-list');
    }

    public function testMysqlInfoList(): void {
        $mysqlInfo = new DB\MysqlInfo(
            MysqlTableMode::all,
            MysqlInfoOrderBy::tableName,
            Direction::descending,
        );
        $list = $mysqlInfo->info();
        $this->assertEquals('xbt_snatched', array_first($list)['table_name'], 'mysqlinfo-list');
    }
}
