<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class JsonStatsTest extends TestCase {
    public function testGeneral(): void {
        $this->assertEqualsCanonicalizing(
            [
                'maxUsers',
                'enabledUsers',
                'usersActiveThisDay',
                'usersActiveThisWeek',
                'usersActiveThisMonth',
                'seederCount',
                'leecherCount',
                'torrentCount',
                'releaseCount',
                'artistCount',
                'perfectFlacCount',
                'requestCount',
                'filledRequestCount',
            ],
            array_keys(new Json\Stats\General()->payload()),
            'json-stats-general'
        );
    }
}
