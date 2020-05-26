<?php

use \Gazelle\Manager\Notification;

$Skip = [];
$Skip[] = db_string($_GET['skip']);
$Notification = new Notification($LoggedUser['ID'], $Skip);

json_die("success", $Notification->notifications());
