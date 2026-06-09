<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$recipient = new Manager\User()->findById((int)$_GET['toid']);
if (is_null($recipient)) {
    Error404::error();
}
if ($Viewer->disablePm() && !$recipient->isStaff()) {
    Error403::error();
}
if ($recipient->id === $Viewer->id) {
    Error400::error('You cannot start a conversation with yourself!');
}

echo $Twig->render('inbox/compose.twig', [
    'body'      => new Util\Textarea('body', '', 95, 10),
    'recipient' => $recipient,
    'viewer'    => $Viewer,
]);
