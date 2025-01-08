<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_top10')) {
    json_die('failure');
}

match ($_GET['type'] ?? 'torrents') {
    'tags'     => include_once 'tags.php',
    'torrents' => include_once 'torrents.php',
    'users'    => include_once 'users.php',
    default    => json_error('bad type'),
};
