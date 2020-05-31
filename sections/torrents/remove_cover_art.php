<?php
authorize();
if (!check_perms('site_edit_wiki')) {
    error(403);
}

$ID = $_GET['id'];
$GroupID = $_GET['groupid'];

if (!is_number($ID) || !is_number($ID) || !is_number($GroupID) || !is_number($GroupID)) {
    error(404);
}

list($Image, $Summary) = $DB->row("
    SELECT Image, Summary
    FROM cover_art
    WHERE ID = ?
    ", $ID
);

$DB->prepared_query("
    DELETE FROM cover_art
    WHERE ID = ?
    ", $ID
);

Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "Additional cover \"$Summary - $Image\" removed from group", 0);

$Cache->deleteMulti(["torrents_cover_art_$GroupID", "torrents_details_$GroupID"]);
header("Location: " . empty($_SERVER['HTTP_REFERER']) ? "torrents.php?id={$GroupID}" : $_SERVER['HTTP_REFERER']);
