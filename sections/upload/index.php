<?php

if (!$Viewer->permitted('site_upload')) {
    error("Your userclass does not allow you to upload.");
}
if ($Viewer->disableUpload()) {
    error('Your upload privileges have been revoked.');
}

if (!empty($_GET['action'])) {
    switch($_GET['action']) {
        // This is only used for GazelleSync. This should be moved into an endpoint
        // under ajax.php that is "public facing".
        case 'parse_html':
            require('parse_html.php');
            break;
        case 'parse_json':
            require('parse_json.php');
            break;
        default:
            error(404);
    }
}
elseif (!empty($_POST['submit'])) {
    require('upload_handle.php');
}
else {
    require('upload.php');
}
