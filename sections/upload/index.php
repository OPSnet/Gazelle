<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_upload')) {
    error("Your userclass does not allow you to upload.");
}
if ($Viewer->disableUpload()) {
    error('Your upload privileges have been revoked.');
}

if (isset($_GET['action']) && $_GET['action'] == 'parse_html') {
    include_once 'parse_html.php';
} elseif (!empty($_POST['submit'])) {
    include_once 'upload_handle.php';
} else {
    include_once 'upload.php';
}
