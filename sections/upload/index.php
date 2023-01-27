<?php

if (!$Viewer->permitted('site_upload')) {
    error("Your userclass does not allow you to upload.");
}
if ($Viewer->disableUpload()) {
    error('Your upload privileges have been revoked.');
}

if (!empty($_GET['action'])) {
    match ($_GET['action']) {
        'parse_html' => require_once('parse_html.php'),
        'parse_json' => require_once('parse_json.php'),
        default      => error(404),
    };
}
elseif (!empty($_POST['submit'])) {
    require_once('upload_handle.php');
}
else {
    require_once('upload.php');
}
