<?php
enforce_login();
if (!check_perms('site_upload')) {
    error(403);
}
if ($LoggedUser['DisableUpload']) {
    error('Your upload privileges have been revoked.');
}

if (!empty($_GET['action'])) {
    switch($_GET['action']) {
        case 'parse_html':
            include SERVER_ROOT.'/sections/upload/parse_html.php';
            break;
        default:
            error(404);
    }
}
elseif (!empty($_POST['submit'])) {
    include(SERVER_ROOT . '/sections/upload/upload_handle.php');
}
else {
    include(SERVER_ROOT.'/sections/upload/upload.php');
}
