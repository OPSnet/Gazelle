<?php
//calculate ratio
//returns 0 for DNE and -1 for infinity, because we don't want strings being returned for a numeric value in our java
$Ratio = 0;
if ($LoggedUser['BytesUploaded'] == 0 && $LoggedUser['BytesDownloaded'] == 0) {
    $Ratio = 0;
} elseif ($LoggedUser['BytesDownloaded'] == 0) {
    $Ratio = -1;
} else {
    $Ratio = number_format(max($LoggedUser['BytesUploaded'] / $LoggedUser['BytesDownloaded'] - 0.005, 0), 2); //Subtract .005 to floor to 2 decimals
}

$ClassLevels = (new Gazelle\Manager\User)->classLevelList();

json_print("success", [
    'username' => $Viewer->username(),
    'id'       => $Viewer->id(),
    'authkey'  => $Viewer->auth(),
    'passkey'  => $Viewer->announceKey(),
    'notifications' => [
        'messages'         => $Viewer->inboxUnreadCount(),
        'notifications'    => $Viewer->unreadTorrentNotifications(),
        'newAnnouncement'  => (new \Gazelle\Manager\News)->latest() < (new \Gazelle\WitnessTable\UserReadNews)->lastRead($Viewer->id()),
        'newBlog'          => (new \Gazelle\Manager\Blog)->latest() < (new \Gazelle\WitnessTable\UserReadBlog)->lastRead($Viewer->id()),
        'newSubscriptions' => (new \Gazelle\Manager\Subscription($Viewer->id()))->unread() > 0,
    ],
    'userstats' => [
        'uploaded' => (int)$LoggedUser['BytesUploaded'],
        'downloaded' => (int)$LoggedUser['BytesDownloaded'],
        'ratio' => (float)$Ratio,
        'requiredratio' => (float)$LoggedUser['RequiredRatio'],
        'bonusPoints' => (int)$LoggedUser['BonusPoints'],
        'bonusPointsPerHour' => (float)number_format($LoggedUser['BonusPointsPerHour'], 2),
        'class' => $ClassLevels[$LoggedUser['Class']]['Name']
    ]
]);
