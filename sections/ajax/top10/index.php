<?php

if (!$Viewer->permitted('site_top10')) {
    json_die('failure');
}

match ($_GET['type'] ?? 'torrents') {
    'history'  => require_once('history.php'),
    'tags'     => require_once('tags.php'),
    'torrents' => require_once('torrents.php'),
    'users'    => require_once('users.php'),
    default    => json_error('bad type'),
};
