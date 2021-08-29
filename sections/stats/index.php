<?php

switch ($_REQUEST['action'] ?? null) {
    case 'users':
        require('users.php');
        break;
    case 'torrents':
        require('torrents.php');
        break;
    default:
        require('list.php');
        break;
}
