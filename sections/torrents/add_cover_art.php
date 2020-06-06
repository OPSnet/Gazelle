<?php
authorize();

if (!check_perms('site_edit_wiki')) {
    error(403);
}

$UserID = $LoggedUser['ID'];
$GroupID = db_string($_POST['groupid']);
$Summaries = $_POST['summary'];
$Images = $_POST['image'];
$Time = sqltime();

if (!is_number($GroupID) || !$GroupID) {
    error(0);
}

if (count($Images) != count($Summaries)) {
    error('Missing an image or a summary');
}

$Changed = false;
for ($i = 0; $i < count($Images); $i++) {
    $Image = $Images[$i];
    $Summary = $Summaries[$i];

    if (ImageTools::blacklisted($Image, true) || !preg_match("/^".IMAGE_REGEX."$/i", $Image)) {
        continue;
    }

    $DB->prepared_query("
        INSERT IGNORE INTO cover_art
               (GroupID, Image, Summary, UserID, Time)
        VALUES (?,       ?,     ?,       ?,      ?)
        ", $GroupID, trim($Image), trim($Summary), $UserID, $Time
    );
    if ($DB->affected_rows()) {
        $Changed = true;
        Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "Additional cover \"$Summary - $Image\" added to group", 0);
    }
}

if ($Changed) {
    $Cache->delete_value("torrents_cover_art_$GroupID");
}

header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$GroupID}");
