<?php

authorize();
$DB->prepared_query("
    DELETE FROM friends
    WHERE UserID = ?
        AND FriendID = ?
    ", $LoggedUser['ID'], (int)$_POST['friendid']
);

header('Location: friends.php');
