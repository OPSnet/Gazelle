<?php

authorize();

//Set by system
if (!$_POST['groupid'] || !is_number($_POST['groupid'])) {
    error(404);
}
$GroupID = $_POST['groupid'];

//Usual perm checks
if (!check_perms('torrents_edit')) {
    $DB->query("
        SELECT UserID
        FROM torrents
        WHERE GroupID = $GroupID");
    if (!in_array($LoggedUser['ID'], $DB->collect('UserID'))) {
        error(403);
    }
}

if (isset($_POST['freeleechtype']) && check_perms('torrents_freeleech')) {
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

    Torrents::freeleech_groups($GroupID, $Free, $FreeType);
}

//Escape fields
$Year = db_string((int)$_POST['year']);
$RecordLabel = db_string($_POST['record_label']);
$CatalogueNumber = db_string($_POST['catalogue_number']);

// Get some info for the group log
$OldYear = $DB->scalar("SELECT Year FROM torrents_group WHERE ID = ?", $GroupID);

$DB->prepared_query("
    UPDATE torrents_group SET
        Year = ?,
        RecordLabel = ?,
        CatalogueNumber = ?
    WHERE ID = ?
    ", $Year, $RecordLabel, $CatalogueNumber, $GroupID
);

if ($OldYear != $Year) {
    Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "Year changed from $OldYear to $Year", 0);
}

$DB->prepared_query("
    SELECT ID
    FROM torrents
    WHERE GroupID = ?
    ", $GroupID
);
while (list($TorrentID) = $DB->next_record()) {
    $Cache->delete_value("torrent_download_$TorrentID");
}
Torrents::update_hash($GroupID);
$Cache->delete_value("torrents_details_$GroupID");

header("Location: torrents.php?id=$GroupID");
