<?php

$notifMan = new Gazelle\Manager\Notification($LoggedUser['ID']);
foreach ($_GET['type'] as $type) {
    $notifMan->setType($type);
}

json_print('success', $notifMan->notifications());
