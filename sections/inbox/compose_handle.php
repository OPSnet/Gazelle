<?php

authorize();

$recipient = (new Gazelle\Manager\User)->findById((int)$_POST['toid']);
if (is_null($recipient)) {
    error(404);
}
if ($Viewer->option('DisablePM') && !$recipient->isStaffPMReader()) {
    error(403);
}

$body = trim($_POST['body'] ?? '');
if ($body === '') {
    error('You cannot send a message without a body.');
}

$userMan = new Gazelle\Manager\User;
$pmMan = new Gazelle\Manager\PM($Viewer);
$pm = $pmMan->findById((int)($_POST['convid'] ?? 0));
if ($pm) {
    $userMan->replyPM($recipient->id(), $Viewer->id(), $pm->subject(), $body, $pm->id());
} else {
    $subject = trim($_POST['subject']);
    if (empty($subject)) {
        error('You cannot send a message without a subject.');
    }
    $pm = $recipient->inbox()->create($Viewer, $subject, $body);
}

(new Gazelle\Manager\Notification)->push([$recipient->id()],
    "Message from " . $Viewer->username() . ", Subject: " . $pm->subject(),
    $body,
    SITE_URL . '/inbox.php',
    Gazelle\Manager\Notification::INBOX
);

header("Location: inbox.php");
