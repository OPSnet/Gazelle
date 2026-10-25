<?php

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Exception\EarlyExitException;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
    public function testRouterBasic(): void {
        global $Cache, $Debug, $Twig;
        $ctx = new RequestContext('/index.php', '127.0.0.1', '');
        $resolved = Router::resolve($ctx, $Cache, $Debug, $Twig);
        $this->assertInstanceOf('\\Gazelle\\Route\\Index\\Unauthenticated', $resolved, 'router-basic');
    }

    public function testRouterLegacy(): void {
        global $Cache, $Debug, $Twig;
        $ctx = new RequestContext('/recovery.php', '127.0.0.1', '');
        $resolved = Router::resolve($ctx, $Cache, $Debug, $Twig);
        $this->assertInstanceOf('\\Gazelle\\LegacyRoute', $resolved, 'router-legacy-class');
        $this->assertNotFalse($resolved->resolveFile($ctx), 'router-legacy-file');
    }

    public function testRouterInvalid(): void {
        global $Cache, $Debug, $Twig;
        $ctx = new RequestContext('/thismoduledoesnotexist.php', '127.0.0.1', '');
        Base::setRequestContext($ctx);
        $this->assertFalse(LegacyRoute::resolveFile($ctx), 'router-invalid-resolve');

        $this->expectOutputRegex("#Additional details: invalid module name#");
        $this->expectException(EarlyExitException::class);
        Router::resolve($ctx, $Cache, $Debug, $Twig);
    }

    public function testRouterInvalid2(): void {
        global $Cache, $Debug, $Twig;
        $ctx = new RequestContext('/μηέγκυρη.php', '127.0.0.1', '');
        Base::setRequestContext($ctx);

        $this->expectOutputRegex("#Additional details: invalid module name#");
        $this->expectException(EarlyExitException::class);
        Router::resolve($ctx, $Cache, $Debug, $Twig);
    }
}
