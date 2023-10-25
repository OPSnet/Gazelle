<?php

authorize();

$userMan = new Gazelle\Manager\User;
$recipient = $userMan->findById((int)($_POST['toid'] ?? 0));
if (is_null($recipient)) {
    error("No such recipient!");
}

$subject = trim($_POST['subject']);
if (empty($subject)) {
    error("You can't send a message without a subject.");
}

$body = trim($_POST['body'] ?? '');
if ($body === '') {
    error("You can't send a message without a body!");
}

$userMan->sendPM($recipient->id(), $Viewer->id(), $subject, $body);

header('Location: reports.php');
