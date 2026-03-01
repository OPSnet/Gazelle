<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    json_die('not allowed');
}

require_once __DIR__ . '/' . match ($_GET['type'] ?? 'main') {
    'main'       => 'main.php',
    'viewforum'  => 'forum.php',
    'viewthread' => 'thread.php',
    default      => json_error('type'),
};
