<?php

authorize();

$FriendID = (int)$_GET['friendid'];
if (!$FriendID || !$DB->scalar("SELECT 1 FROM users_main WHERE ID = ?", $FriendID)) {
    error(404);
}

$DB->prepared_query("
    INSERT IGNORE INTO friends
           (UserID, FriendID)
    VALUES (?,      ?)
    ", $LoggedUser['ID'], $FriendID
);

header('Location: friends.php');
