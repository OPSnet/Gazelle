<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$recipient = (new Gazelle\Manager\User())->findById((int)$_GET['toid']);
if (is_null($recipient)) {
    error(404);
}
if ($Viewer->disablePm() && !$recipient->isStaff()) {
    error(403);
}
if ($recipient->id() == $Viewer->id()) {
    error('You cannot start a conversation with yourself!');
}

echo $Twig->render('inbox/compose.twig', [
    'body'      => new Gazelle\Util\Textarea('body', '', 95, 10),
    'recipient' => $recipient,
    'viewer'    => $Viewer,
]);
