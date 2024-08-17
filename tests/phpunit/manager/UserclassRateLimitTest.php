<?php

use PHPUnit\Framework\TestCase;

class UserclassRateLimitTest extends TestCase {
    public function testUserclassRateLimit(): void {
        $limiter = new Gazelle\Manager\UserclassRateLimit();
        $list = $limiter->list();
        $this->assertIsArray($list, 'userclass-ratelimit-initial'); // validate the SQL query

        $db = Gazelle\DB::DB();
        /* Find a free secondary userclass. The code does not pay attention
         * to secondary classes for real, this is just a simple way to test
         * which will leave existing records alone.
         * If this test fails, you need to create an unused secondary userclass.
         */
        $freeId = (int)$db->scalar("
            SELECT p.id
            FROM permissions p
            LEFT JOIN permission_rate_limit prl ON (prl.permission_id = p.id)
            WHERE p.secondary = 1
                AND prl.permission_id IS NULL
        ");

        $this->assertEquals(1, $limiter->save($freeId, 1.25, 20), 'userclass-ratelimit-save');
        $this->assertEquals(count($list) + 1, count($limiter->list()), 'userclass-ratelimit-new-list');
        $this->assertEquals(1, $limiter->remove($freeId), 'userclass-ratelimit-remove');
        $this->assertEquals(count($list), count($limiter->list()), 'userclass-ratelimit-revert-list');
    }
}
