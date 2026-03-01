<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_top10')) {
    json_die('not allowed');
}

require_once __DIR__ . '/' . match ($_GET['type'] ?? 'torrents') {
    'tags'     => 'tags.php',
    'torrents' => 'torrents.php',
    'users'    => 'users.php',
    default    => json_error('bad type'),
};
