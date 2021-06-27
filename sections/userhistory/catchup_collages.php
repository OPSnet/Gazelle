<?php

authorize();

$notifMan = new \Gazelle\Manager\Notification($Viewer->id());
if ($_REQUEST['collageid'] && (int)$_REQUEST['collageid']) {
    $notifMan->catchupCollage($_REQUEST['collageid']);
} else {
    $notifMan->catchupAllCollages();
}

header('Location: userhistory.php?action=subscribed_collages');
