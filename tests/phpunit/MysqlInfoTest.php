<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class MysqlInfoTest extends TestCase {
    public function testMysqlInfoColumn(): void {
        $this->assertCount(10, DB\MysqlInfo::columnList(), 'myinfo-column-list');
    }

    public function testMysqlInfoList(): void {
        $mysqlInfo = new DB\MysqlInfo(
            Enum\MysqlTableMode::all,
            Enum\MysqlInfoOrderBy::tableName,
            Enum\Direction::descending,
        );
        $list = $mysqlInfo->info();
        $this->assertSame('xbt_snatched', array_first($list)['table_name'], 'mysqlinfo-list');
    }
}
