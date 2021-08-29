<?php

if (!$Viewer->permitted('site_top10')) {
    json_die('failure');
}

switch ($_GET['type'] ?? 'torrents') {
    case 'users':
        require_once('users.php');
        break;
    case 'tags':
        require_once('tags.php');
        break;
    case 'history':
        require_once('history.php');
        break;
    case 'torrents':
        require_once('torrents.php');
        break;
    default:
        print json_encode(['status' => 'failure']);
        break;
}
