<?php

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(403);
}
if (!$Viewer->permitted('users_mod') && $user->id() != $Viewer->id()) {
    error(403);
}
authorize();

(new Gazelle\Manager\Notification)->push($user->id(),
    'Push!', 'You have been pushed by ' . $Viewer->username());

header('Location: ' . $user->url() . '&action=edit');
