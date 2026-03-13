<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    json_die('not allowed');
}

require __DIR__ . match ($_GET['type'] ?? '') {
    'viewforum'  => '/forum.php',
    'viewthread' => '/thread.php',
    default      => '/main.php',
};
