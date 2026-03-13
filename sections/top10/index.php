<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_top10')) {
    Error403::error('Your class does not have permission to view the top 10 lists.');
}

require __DIR__ . match ($_GET['type'] ?? '') {
    'donors'  => '/donors.php',
    'history' => '/history.php',
    'lastfm'  => '/lastfm.php',
    'tags'    => '/tags.php',
    'users'   => '/users.php',
    'votes'   => '/votes.php',
    default   => '/torrents.php',
};
