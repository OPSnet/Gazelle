<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$manager = new Manager\User();
$recipient = null;
if (isset($_GET['toid'])) {
    $recipient = $manager->findById((int)$_GET['toid']);
} elseif (isset($_GET['recipient'])) {
    $recipient = $manager->findByUsername($_GET['recipient']);
}
if ($recipient === null) {
    Error404::error("no such user");
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
