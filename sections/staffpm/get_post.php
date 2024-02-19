<?php

//TODO: make this use the cache version of the thread, save the db query
/*********************************************************************\
//--------------Get Post--------------------------------------------//

This gets the raw BBCode of a post. It's used for editing and
quoting posts.

It gets called if $_GET['action'] == 'get_post'. It requires
$_GET['post'], which is the ID of the post.

\*********************************************************************/

$PostID = (int)$_GET['post'];
if (!$PostID) {
    error(404);
}

// Variables for database input

// Message is selected providing the user quoting is the guy who opened the PM or has
// the right level
[$Message, $Level, $UserID] = Gazelle\DB::DB()->row("
    SELECT m.Message, c.Level, c.UserID
    FROM staff_pm_messages AS m
    INNER JOIN staff_pm_conversations AS c ON (m.ConvID = c.ID)
    WHERE m.ID = ?
    ", $PostID
);

if (($Viewer->id() == $UserID) || ($Viewer->isStaffPMReader() && $Viewer->privilege()->effectiveClassLevel() >= $Level)) {
    // This gets sent to the browser, which echoes it wherever
    header('Content-type: text/plain');
    echo $Message;
} else {
    error(403);
}
