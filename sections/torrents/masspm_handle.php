<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('site_moderate_requests')) {
    Error403::error();
}
$torrent = new Manager\Torrent()->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    Error404::error();
}

$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

$validator = new Util\Validator();
$validator->setFields([
    ['subject', false, 'string', 'Invalid subject.', ['maxlength' => 1000]],
    ['message', false, 'string', 'Invalid message.', ['maxlength' => 10000]],
]);
if (!$validator->validate($_POST)) {
    Error400::error($validator->errorMessage());
}

new Manager\User()->sendSnatchPm($Viewer, $torrent, $subject, $message);
header("Location: " . $torrent->location());
