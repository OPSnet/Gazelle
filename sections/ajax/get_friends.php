<?php

$DB->prepared_query("
    SELECT f.FriendID,
        u.Username
    FROM friends AS f
    RIGHT JOIN users_main AS u ON (u.ID = f.FriendID)
    WHERE f.UserID = ?
    ORDER BY u.Username ASC
    ", $Viewer->id()
);
echo json_encode($DB->to_array(false, MYSQLI_ASSOC));
