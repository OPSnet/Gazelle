<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)$_POST['convid']);
if (is_null($pm)) {
    error('Sorry, there is no trace of that conversation in your folder');
}
$recipient = (new Gazelle\Manager\User())->findById((int)$_POST['receiverid']);
if (is_null($recipient)) {
    error('Sorry, there is no-one here by that name');
}
if (!$Viewer->permitted('users_mod') && !$recipient->isStaffPMReader()) {
    error(403);
}

if (in_array($recipient->id(), $pm->recipientList())) {
    error($recipient->username() . " already has this conversation in their inbox.");
}

$pm->setForwardedTo($recipient->id());
header('Location: inbox.php');
