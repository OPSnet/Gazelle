<?php

$postId = (int)$_GET['post'];
// Quick SQL injection check
if (!$postId) {
    error(404);
}

// Message is selected providing the user quoting is one of the two people in the thread
$body = Gazelle\DB::DB()->scalar("
    SELECT m.Body
    FROM pm_messages AS m
    INNER JOIN pm_conversations_users AS u USING (ConvID)
    WHERE u.UserID = ?
        AND m.ID = ?
    ", $Viewer->id(), $postId
);

// This gets sent to the browser, which echoes it wherever
echo trim($body);
