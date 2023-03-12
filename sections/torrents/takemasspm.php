<?php

authorize();

if (!$Viewer->permitted('site_moderate_requests')) {
    error(403);
}
$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    error(404);
}

$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['subject', false, 'string', 'Invalid subject.', ['maxlength' => 1000]],
    ['message', false, 'string', 'Invalid message.', ['maxlength' => 10000]],
]);
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}

(new Gazelle\Manager\User)->sendSnatchPm($Viewer, $torrent, $subject, $message);
header("Location: " . $torrent->location());
