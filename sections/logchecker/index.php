<?
enforce_login();
if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'test':
            require(SERVER_ROOT.'/sections/logchecker/test.php');
            break;
        case 'take_test':
            // upload one log and see what it would score in our log checker
            require(SERVER_ROOT.'/sections/logchecker/take_test.php');
            break;
        case 'upload':
            require(SERVER_ROOT.'/sections/logchecker/upload.php');
            break;
        case 'update':
            // Update torrents that have logs, regardless of score
            require(SERVER_ROOT.'/sections/logchecker/update.php');
            break;
        case 'take_upload':
            // this actually saves a log for a torrent
            require(SERVER_ROOT.'/sections/logchecker/take_upload.php');
            break;
        default:
            error(404);
    }
} else {
    require(SERVER_ROOT.'/sections/logchecker/test.php');
}
