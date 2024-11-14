<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_top10')) {
    $Twig->render('top10/dissabled.twig');
    exit();
}

require_once match ($_GET['type'] ?? 'torrents') {
    'donors'  => 'donors.php',
    'history' => 'history.php',
    'lastfm'  => 'lastfm.php',
    'tags'    => 'tags.php',
    'users'   => 'users.php',
    'votes'   => 'votes.php',
    default   => 'torrents.php',
};
