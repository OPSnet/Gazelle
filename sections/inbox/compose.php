<?php

use Gazelle\Inbox;

$recipient = (new Gazelle\Manager\User)->findById((int)$_GET['toid']);
if (is_null($recipient)) {
    error(404);
}
if ($Viewer->disablePm() && !$recipient->isStaff()) {
    error(403);
}
if (!isset($Return) && $recipient->id() == $Viewer->id()) {
    error('You cannot start a conversation with yourself!');
}

echo $Twig->render('inbox/compose.twig', [
    'auth'      => $Viewer->auth(),
    'body'      => $Body ?? '',
    'subject'   => $Subject ?? '',
    'recipient' => $recipient,
]);
