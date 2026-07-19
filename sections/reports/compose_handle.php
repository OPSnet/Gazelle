<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$recipient = new Manager\User()->findById((int)($_POST['toid'] ?? 0));
if (is_null($recipient)) {
    Error404::error("No such recipient!");
}

$subject = trim($_POST['subject']);
if (empty($subject)) {
    Error400::error("You can't send a message without a subject.");
}
$body = trim($_POST['body'] ?? '');
if ($body === '') {
    Error400::error("You can't send a message without a body!");
}

$recipient->inbox()->create($Viewer, $subject, $body);

header('Location: reports.php');
