<?php

$alertList = (new Gazelle\User\Notification($Viewer))->alertList();

$payload = [];
foreach ($alertList as $alert) {
    if (!isset($_GET['type']) || isset($_GET['type'][$alert->type()])) {
        $payload[$alert->type()] = [
            'id'         => $alert->context(),
            'importance' => $alert->className(),
            'message'    => $alert->title(),
            'url'        => $alert->notificationUrl(),
        ];
    }
}

json_print('success', $payload);
