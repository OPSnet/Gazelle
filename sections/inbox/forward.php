<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$pm = new Manager\PM($Viewer)->findById((int)$_POST['convid']);
if (is_null($pm)) {
    Error404::error('Sorry, there is no trace of that conversation in your folder');
}
$recipient = new Manager\User()->findById((int)$_POST['receiverid']);
if (is_null($recipient)) {
    Error404::error('Sorry, there is no-one here by that name');
}
if (!$Viewer->permitted('users_mod') && !$recipient->isStaffPMReader()) {
    Error403::error();
}

if (in_array($recipient->id, $pm->recipientList())) {
    Error400::error(
        "{$recipient->username()} already has this conversation in their inbox."
    );
}

$pm->setForwardedTo($recipient->id);
header('Location: inbox.php');
