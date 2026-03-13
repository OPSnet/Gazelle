<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_top10')) {
    json_die('not allowed');
}

require __DIR__ . match ($_GET['type'] ?? '') {
    'tags'     => '/tags.php',
    'users'    => '/users.php',
    default    => '/torrents.php',
};
