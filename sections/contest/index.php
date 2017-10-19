<?
enforce_login();

include(SERVER_ROOT.'/sections/contest/config.php');

if (isset($_GET['leaderboard']) && $_GET['leaderboard'] == 1) {
    include(SERVER_ROOT.'/sections/contest/leaderboard.php');
}
elseif (isset($_GET['theunitadmin']) && $_GET['theunitadmin'] == 1) {
    include(SERVER_ROOT.'/sections/contest/admin.php');
}
else {
    include(SERVER_ROOT.'/sections/contest/intro.php');
}
