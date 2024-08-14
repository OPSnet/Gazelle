<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$notifier = new Gazelle\User\Notification\Collage($Viewer);
if (!isset($_REQUEST['collageid'])) {
    $notifier->clear();
} else {
    if ((int)$_REQUEST['collageid']) {
        $notifier->clearCollage($_REQUEST['collageid']);
    } else {
        error(404);
    }
}

header('Location: userhistory.php?action=subscribed_collages');
