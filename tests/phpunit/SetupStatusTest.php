<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class SetupStatusTest extends TestCase {
    public function testStatus(): void {
        // Unit tests are not run in isolation (for performance reasons)
        // Since request contexts leak from previous tests, create a fresh one.
        Base::setRequestContext(
            new RequestContext(
                scriptName: 'none',
                remoteAddr: '127.0.0.1',
                useragent:  'phpunit',
            )
        );
        foreach (Util\SetupStatus::check() as [$thing, $status]) {
            $this->assertNull($status, "setup status error on {$thing->value}");
        }
    }
}
