<?php

use Gazelle\Manager\Notification;

if (!$Viewer->permitted("admin_global_notification")) {
    error(404);
}
$notification = new Notification;
if ($_POST['set']) {
    $Expiration = $_POST['length'] * 60;
    $notification->setGlobal($_POST['message'], $_POST['url'], $_POST['importance'], $Expiration);
} elseif ($_POST['delete']) {
    $notification->deleteGlobal();
}

header("Location: tools.php?action=global_notification");
