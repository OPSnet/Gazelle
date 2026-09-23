<?php

declare(strict_types=1);

namespace Gazelle;

/**
 * legacy route module that redirects to sections/
 */
class LegacyRoute extends Route {
    public function action(): ?string {
        // best-effort, we have no verification for legacy modules
        return $_REQUEST['action'] ?? null;
    }

    public function process(): void {
        $Cache  = $this->cache;
        $Debug  = $this->debug;
        $Twig   = $this->twig;
        $Viewer = $this->ctx->hasViewer() ? $this->ctx->viewer() : null;
        require self::resolveFile($this->ctx);
    }

    public static function resolveFile(RequestContext $ctx): string|false {
        return realpath(__DIR__ . "/../sections/{$ctx->module()}/index.php");
    }
}
