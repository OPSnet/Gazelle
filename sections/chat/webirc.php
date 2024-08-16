<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->disableIRC() && !$Viewer->IRCKey()) {
    $ircKey = randomString(32);
    $Viewer->setField('IRCKey', $ircKey)->modify();
}

$ircNick = sanitize_irc_nick($Viewer->username());

echo $Twig->render('chat/webirc.twig', [
    'user'     => $Viewer,
    'irc_nick' => $ircNick,
]);
