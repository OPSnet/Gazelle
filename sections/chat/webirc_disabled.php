<?php
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$ircNick = sanitize_irc_nick($Viewer?->username() ?? $_GET['nick'] ?? '');

echo $Twig->render('chat/webirc.twig', [
    'irc_nick' => $ircNick,
]);
