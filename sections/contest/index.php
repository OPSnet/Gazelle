<?php
enforce_login();

$ContestMgr = new \Gazelle\Contest;

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'leaderboard':
            include(SERVER_ROOT . '/sections/contest/leaderboard.php');
            break;
        case 'admin':
        case 'create':
            include(SERVER_ROOT . '/sections/contest/admin.php');
            break;
    }
}
else {
    include(SERVER_ROOT.'/sections/contest/intro.php');
}
