<?php

/* TODO: This is an ajax call: move to sections/ajax */

authorize();
if (!check_perms('site_edit_wiki')) {
    error(403);
}

$coverId = (int)$_GET['id'];
$groupId = (int)$_GET['groupid'];

if (!$coverId || !$groupId) {
    error(404);
}

[$image, $summary] = $DB->row("
    SELECT Image, Summary
    FROM cover_art
    WHERE ID = ?
    ", $coverId
);
$DB->prepared_query("
    DELETE FROM cover_art
    WHERE ID = ?
    ", $coverId
);

(new Gazelle\Log)->group($groupId, $Viewer->id(), "Additional cover \"$summary - $image\" removed from group");

$Cache->deleteMulti(["torrents_cover_art_$groupId", "torrents_details_$groupId"]);
