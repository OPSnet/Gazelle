<?
enforce_login();
if (isset($_GET['method'])) {
    switch ($_GET['method']) {
        case 'transcode':
            include(SERVER_ROOT.'/sections/better/transcode.php');
            break;
        case 'missing':
            include(SERVER_ROOT.'/sections/better/missing.php');
            break;
        case 'single':
            include(SERVER_ROOT.'/sections/better/single.php');
            break;
        default:
            error(404);
            break;
    }
} else {
    include(SERVER_ROOT.'/sections/better/transcode.php');
}
?>
