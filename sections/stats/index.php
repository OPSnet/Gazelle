<?php

switch ($_REQUEST['action'] ?? null) {
    case 'users':
        require_once('users.php');
        break;
    case 'torrents':
        require_once('torrents.php');
        break;
    default:
        echo $Twig->render('stats.twig');
        break;
}
