<?php
authorize();

$GroupID = $_POST['groupid'];
$OldGroupID = $GroupID;
$NewName = $_POST['name'];

if (!$GroupID || !is_number($GroupID)) {
    error(404);
}

if (empty($NewName)) {
    error('Torrent groups must have a name');
}

if (!check_perms('torrents_edit')) {
    error(403);
}

$OldName = $DB->scalar("
    SELECT Name
    FROM torrents_group
    WHERE ID = ?
    ", $GroupID
);

$DB->prepared_query("
    UPDATE torrents_group SET
        Name = ?
    WHERE ID = ?
    ", $NewName, $GroupID
);

(new \Gazelle\Manager\TGroup)->refresh($GroupID);
(new Gazelle\Log)->group($GroupID, $Viewer->id(), "renamed to \"$NewName\" from \"$OldName\"")
    ->general("Torrent Group $GroupID ($OldName) was renamed to \"$NewName\" from \"$OldName\" by " . $Viewer->username());

header("Location: torrents.php?id=$GroupID");
