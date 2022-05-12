<?php
//calculate ratio
//returns 0 for DNE and -1 for infinity, because we don't want strings being returned for a numeric value in our java
$Ratio = 0;
if ($Viewer->uploadedSize() == 0 && $Viewer->downloadedSize() == 0) {
    $Ratio = 0;
} elseif ($Viewer->downloadedSize() == 0) {
    $Ratio = -1;
} else {
    $Ratio = number_format(max($Viewer->uploadedSize() / $Viewer->downloadedSize() - 0.005, 0), 2); //Subtract .005 to floor to 2 decimals
}

$ClassLevels = (new Gazelle\Manager\User)->classLevelList();
$latestBlog = (new Gazelle\Manager\Blog)->latest();

json_print("success", [
    'username' => $Viewer->username(),
    'id'       => $Viewer->id(),
    'authkey'  => $Viewer->auth(),
    'passkey'  => $Viewer->announceKey(),
    'notifications' => [
        'messages'         => $Viewer->inboxUnreadCount(),
        'notifications'    => (new Gazelle\User\Notification\Torrent($Viewer))->unread(),
        'newAnnouncement'  => (new Gazelle\Manager\News)->latestId() < (new Gazelle\WitnessTable\UserReadNews)->lastRead($Viewer->id()),
        'newBlog'          => $latestBlog && $latestBlog->createdEpoch() < (new Gazelle\WitnessTable\UserReadBlog)->lastRead($Viewer->id()),
        'newSubscriptions' => (new Gazelle\User\Subscription($Viewer))->unread() > 0,
    ],
    'userstats' => [
        'uploaded'           => $Viewer->uploadedSize(),
        'downloaded'         => $Viewer->downloadedSize(),
        'ratio'              => (float)$Ratio,
        'requiredratio'      => $Viewer->requiredRatio(),
        'bonusPoints'        => $Viewer->bonusPointsTotal(),
        'bonusPointsPerHour' => round((new Gazelle\User\Bonus($Viewer))->hourlyRate(), 2),
        'class'              => $Viewer->userclassName(),
    ]
]);
