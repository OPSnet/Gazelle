<?php

authorize();

$groupId = (int)$_POST['groupid'];
if (!$groupId) {
    error(404);
}

//Usual perm checks
if (!$Viewer->permitted('torrents_edit')) {
    if (!$DB->scalar("
        SELECT ID FROM torrents WHERE GroupID = ? AND UserID = ?
        ", $groupId, $Viewer->id()
    )) {
        error(403);
    }
}

$log = [];
if (isset($_POST['freeleechtype']) && $Viewer->permitted('torrents_freeleech')) {
    if (in_array($_POST['freeleechtype'], ['0', '1', '2'])) {
        $Free = $_POST['freeleechtype'];
    } else {
        $Free = '0';
    }

    if (isset($_POST['freeleechreason']) && in_array($_POST['freeleechreason'], ['0', '1', '2', '3'])) {
        $FreeType = $_POST['freeleechreason'];
    } else {
        error(404);
    }
    $log[] = "freeleech type=$Free reason=$FreeType";
    Torrents::freeleech_groups($groupId, $Free, $FreeType);
}

$year = (int)trim($_POST['year']);
$recordLabel = trim($_POST['record_label']);
$catNumber = trim($_POST['catalogue_number']);

// Get some info for the group log
[$oldYear, $oldRecLabel, $oldCatNumber] = $DB->row("
    SELECT Year, RecordLabel, CatalogueNumber FROM torrents_group WHERE ID = ?
", $groupId);

if ($oldYear != $year) {
    $log[] = "year $oldYear => $year";
}
if ($oldRecLabel != $recordLabel) {
    $log[] = "record label \"$oldRecLabel\" => \"$recordLabel\"";
}
if ($oldCatNumber != $catNumber) {
    $log[] = "cat number \"$oldCatNumber\" => \"$catNumber\"";
}

if ($log) {
    $DB->prepared_query("
        UPDATE torrents_group SET
            Year = ?,
            RecordLabel = ?,
            CatalogueNumber = ?
        WHERE ID = ?
        ", $year, $recordLabel, $catNumber, $groupId
    );
    (new Gazelle\Log)->group($groupId, $Viewer->id(), ucfirst(implode(", ", $log)));

    $DB->prepared_query("
        SELECT concat('torrent_download_', ID) as cachekey
        FROM torrents
        WHERE GroupID = ?
        ", $groupId
    );
    $Cache->deleteMulti($DB->collect('cacheKey'));

    (new \Gazelle\Manager\TGroup)->refresh($groupId);
}

header("Location: torrents.php?id=$groupId");
