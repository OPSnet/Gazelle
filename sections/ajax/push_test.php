<?php
/** @phpstan-var \Gazelle\User $Viewer */
use Gazelle\Enum\NotificationType;

$notifMan = new Gazelle\Manager\Notification();
$pushTokens = [(new Gazelle\User\Notification($Viewer))->pushToken()];
$notifMan->push($pushTokens, "Notification Test", "Hello " . $Viewer->username() . ". If you can read this, you have set up your push notifications correctly.", "");
