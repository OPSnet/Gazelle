<?php

if (!$Viewer->permitted('site_top10')) {
    $Twig->render('top10/dissabled.twig');
    die();
}

switch ($_GET['type'] ?? 'torrents') {
    case 'torrents':
        require_once('torrents.php');
        break;
    case 'users':
        require_once('users.php');
        break;
    case 'tags':
        require_once('tags.php');
        break;
    case 'history':
        require_once('history.php');
        break;
    case 'votes':
        require_once('votes.php');
        break;
    case 'donors':
        require_once('donors.php');
        break;
    case 'lastfm':
        require_once('lastfm.php');
        break;
}
