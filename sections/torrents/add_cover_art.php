<?php
authorize();

if (!check_perms('site_edit_wiki')) {
    error(403);
}

$GroupID = (int)$_POST['groupid'];
$Summaries = $_POST['summary'];
$Images = $_POST['image'];

if (!$GroupID) {
    error(0);
}

if (count($Images) != count($Summaries)) {
    error('Missing an image or a summary');
}

$Changed = false;
for ($i = 0; $i < count($Images); $i++) {
    $Image = trim($Images[$i]);
    if (ImageTools::blacklisted($Image, true) || !preg_match(IMAGE_REGEXP, $Image)) {
        continue;
    }
    $Summary = trim($Summaries[$i]);

    $DB->prepared_query("
        INSERT IGNORE INTO cover_art
               (GroupID, Image, Summary, UserID, Time)
        VALUES (?,       ?,     ?,       ?,      now())
        ", $GroupID, $Image, $Summary, $Viewer->id()
    );
    if ($DB->affected_rows()) {
        $Changed = true;
        (new Gazelle\Log)->group($GroupID, $Viewer->id(), "Additional cover \"$Summary - $Image\" added to group");
    }
}

if ($Changed) {
    $Cache->delete_value("torrents_cover_art_$GroupID");
}

header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$GroupID}");
