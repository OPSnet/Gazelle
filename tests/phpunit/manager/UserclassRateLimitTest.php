<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class UserclassRateLimitTest extends TestCase {
    public function testUserclassRateLimit(): void {
        $limiter = new Manager\UserclassRateLimit();
        $initial = count($limiter->list()); // validate the SQL query
        $this->assertGreaterThanOrEqual(
            0,
            $initial,
            'userclass-ratelimit-initial'
        );

        /* Find a free secondary userclass. The code does not pay attention
         * to secondary classes for real, this is just a simple way to test
         * which will leave existing records alone.
         * If this test fails, you need to create an unused secondary userclass.
         */
        $freeId = (int)DB::DB()->scalar("
            SELECT p.id
            FROM permissions p
            LEFT JOIN permission_rate_limit prl ON (prl.permission_id = p.id)
            WHERE p.secondary = 1
                AND prl.permission_id IS NULL
        ");

        $this->assertSame(1, $limiter->save($freeId, 1.25, 20), 'userclass-ratelimit-save');
        $this->assertCount($initial + 1, $limiter->list(), 'userclass-ratelimit-new-list');
        $this->assertSame(1, $limiter->remove($freeId), 'userclass-ratelimit-remove');
        $this->assertCount($initial, $limiter->list(), 'userclass-ratelimit-revert-list');
    }
}
