<?php

$notifMan = new Gazelle\Manager\Notification($Viewer->id());
foreach ($_GET['type'] as $type) {
    $notifMan->setType($type);
}

json_print('success', $notifMan->notifications());
