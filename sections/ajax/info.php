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

$user = new Gazelle\User($LoggedUser['ID']);
$ClassLevels = (new Gazelle\Manager\User)->classLevelList();

json_print("success", [
    'username' => $user->username(),
    'id'       => (int)$LoggedUser['ID'],
    'authkey'  => $LoggedUser['AuthKey'],
    'passkey'  => $LoggedUser['torrent_pass'],
    'notifications' => [
        'messages'         => $user->inboxUnreadCount(),
        'notifications'    => $user->unreadTorrentNotifications(),
        'newAnnouncement'  => (new \Gazelle\Manager\News)->latest() < (new \Gazelle\WitnessTable\UserReadNews)->lastRead($user->id()),
        'newBlog'          => (new \Gazelle\Manager\Blog)->latest() < (new \Gazelle\WitnessTable\UserReadBlog)->lastRead($user->id()),
        'newSubscriptions' => (new \Gazelle\Manager\Subscription($LoggedUser['ID']))->unread() > 0,
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
