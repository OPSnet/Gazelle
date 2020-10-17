<?php

authorize();
$DB->prepared_query("
    UPDATE friends SET
        Comment = ?
    WHERE UserID = ?
        AND FriendID = ?
    ", trim($_POST['comment']), $LoggedUser['ID'], (int)$_POST[friendid]
);
header('Location: friends.php');
