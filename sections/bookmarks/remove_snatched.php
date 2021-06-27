<?php

authorize();
$DB->prepared_query("
    DELETE b
    FROM bookmarks_torrents AS b
    INNER JOIN (
        SELECT DISTINCT t.GroupID
        FROM torrents AS t
        INNER JOIN xbt_snatched AS s ON (s.fid = t.ID)
        WHERE s.uid = ?
    ) AS s USING (GroupID)
    WHERE b.UserID = ?
    ", $Viewer->id(), $Viewer->id()
);

$Cache->delete_value("bookmarks_group_ids_" . $Viewer->id());
header('Location: bookmarks.php');
