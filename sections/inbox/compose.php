<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$manager = new Manager\User();
$recipient = $manager->findById((int)$_GET['toid']);
if (is_null($recipient)) {
    // see if we came from inbox composer
    $recipient = $manager->findByUsername($_GET['recipient'] ?? '');
    if (is_null($recipient)) {
        Error404::error();
    }
}
if ($Viewer->disablePm() && !$recipient->isStaff()) {
    Error403::error('You are not allowed to send private messages');
}
if ($recipient->id === $Viewer->id) {
    Error400::error('You cannot start a conversation with yourself!');
}

echo $Twig->render('inbox/compose.twig', [
    'body'      => new Util\Textarea('body', '', 80, 10),
    'recipient' => $recipient,
    'viewer'    => $Viewer,
]);
