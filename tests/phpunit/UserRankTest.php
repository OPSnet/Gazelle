<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserRankTest extends TestCase {
    public function testUserRank(): void {
        $weights = array_intersect_key(RANKING_WEIGHT, ['uploaded' => 1, 'downloaded' => 1]);
        $userRank = new UserRank(
            new \Gazelle\UserRank\Configuration($weights),
            [
                'downloaded' => 0,
                'uploaded' => STARTING_UPLOAD,
            ],
        );
        $this->assertSame(0, $userRank->score(), 'userrank-score');
        $this->assertSame(0, $userRank->rank('uploaded'), 'userrank-uploaded');
        $this->assertSame(STARTING_UPLOAD, $userRank->raw('uploaded'), 'userrank-raw-uploaded');
    }
}
