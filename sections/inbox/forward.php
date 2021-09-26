<?php

authorize();

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)$_GET['id']);
if (is_null($pm)) {
    error(404);
}
$receiver = (new Gazelle\Manager\User)->findById((int)$_POST['receiverid']);
if (is_null($receiver)) {
    error(404);
}
if (!$Viewer->permitted('users_mod') && !$recipient->isStaffPMReader()) {
    error(403);
}

if (in_array($recipient->id(), $pm->recipientList())) {
    error($recipient->username() . " already has this conversation in their inbox.");
    header("Location: inbox.php?action=viewconv&id=" . $pm->id());
    exit;
}

$pm->setForwardedTo($recipient->id());
header('Location: inbox.php');
