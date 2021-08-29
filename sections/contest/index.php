<?php

$contestMan = new Gazelle\Manager\Contest;

switch ($_GET['action'] ?? '') {
    case 'leaderboard':
        require('leaderboard.php');
        break;
    case 'admin':
    case 'create':
        require('admin.php');
        break;
    default:
        require('intro.php');
}
