<?php

use \Gazelle\Manager\Notification;

if (!check_perms("users_mod")) {
    error(404);
}
if ($_POST['set']) {
    $Expiration = $_POST['length'] * 60;
    Notification::set_global_notification($_POST['message'], $_POST['url'], $_POST['importance'], $Expiration);
} elseif ($_POST['delete']) {
    Notification::delete_global_notification();
}

header("Location: tools.php?action=global_notification");
