<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$recipient = new Manager\User()->findById((int)$_POST['toid']);
if (is_null($recipient)) {
    Error404::error();
}
if ($Viewer->option('DisablePM') && !$recipient->isStaffPMReader()) {
    Error403::error();
}

$body = trim($_POST['body'] ?? '');
if ($body === '') {
    Error400::error('You cannot send a message without a body.');
}

$userMan = new Manager\User();
$pmMan = new Manager\PM($Viewer);
$pm = $pmMan->findById((int)($_POST['convid'] ?? 0));
if ($pm) {
    $userMan->replyPM($recipient->id, $Viewer->id, $pm->subject(), $body, $pm->id);
} else {
    $subject = trim($_POST['subject']);
    if (empty($subject)) {
        Error400::error('You cannot send a message without a subject.');
    }
    $pm = $recipient->inbox()->create($Viewer, $subject, $body);
}

header("Location: inbox.php");
