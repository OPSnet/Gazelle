<?php
enforce_login();
if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'test':
            require(__DIR__.'/test.php');
            break;
        case 'take_test':
            // upload one log and see what it would score in our log checker
            require(__DIR__.'/take_test.php');
            break;
        case 'upload':
            require(__DIR__.'/upload.php');
            break;
        case 'update':
            // Update torrents that have logs, regardless of score
            require(__DIR__.'/update.php');
            break;
        case 'take_upload':
            // this actually saves a log for a torrent
            require(__DIR__.'/take_upload.php');
            break;
        default:
            error(404);
    }
} else {
    require(__DIR__.'/test.php');
}
