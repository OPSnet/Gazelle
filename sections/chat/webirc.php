<?php

if (!$Viewer->disableIRC() && !$Viewer->IRCKey()) {
    $ircKey = randomString(32);
    $Viewer->setField('IRCKey', $ircKey)->modify();
}

$userMan = new Gazelle\Manager\User;
$ircNick = str_replace('.', '', $Viewer->username());
if (!$ircNick || $userMan->findByUsername($ircNick)) {
    $ircNick = str_replace('.', '_', $Viewer->username());
    if ($userMan->findByUsername($ircNick)) {
        $ircNick = str_replace('.', '%2E', $Viewer->username());
    }
}

if (is_numeric(substr($ircNick, 0, 1))) {
    $ircNick = '_' . $ircNick;
}

echo $Twig->render('chat/webirc.twig', [
    'user'     => $Viewer,
    'irc_nick' => $ircNick,
]);
