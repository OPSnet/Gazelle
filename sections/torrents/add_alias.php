<?php
authorize();

$UserID = $LoggedUser['ID'];
$GroupID = $_POST['groupid'];
$Importances = $_POST['importance'];
$AliasNames = $_POST['aliasname'];

$GroupName = $DB->scalar('SELECT Name FROM torrents_group WHERE ID = ?', (int)$GroupID);
if (!$GroupName) {
    error(404);
}

$Changed = false;

$ArtistManager = new \Gazelle\Manager\Artist;
for ($i = 0; $i < count($AliasNames); $i++) {
    $AliasName = \Gazelle\Artist::sanitize($AliasNames[$i]);
    $Importance = $Importances[$i];

    if (!in_array($Importance, ['1', '2', '3', '4', '5', '6', '7'])) {
        break;
    }

    if (strlen($AliasName) > 0) {
        $DB->prepared_query('
            SELECT AliasID, ArtistID, Redirect, Name
            FROM artists_alias
            WHERE Name = ?
            ', $AliasName
        );
        while (list($AliasID, $ArtistID, $Redirect, $FoundAliasName) = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($AliasName, $FoundAliasName)) {
                if ($Redirect) {
                    $AliasID = $Redirect;
                }
                break;
            }
        }
        if (!$AliasID) {
            list($ArtistID, $AliasID) = $ArtistManager->createArtist($AliasName);
        }
        $ArtistName = $DB->scalar('SELECT Name FROM artists_group WHERE ArtistID = ?', $ArtistID);

        $DB->prepared_query('
            INSERT IGNORE INTO torrents_artists
                   (GroupID, ArtistID, AliasID, Importance, UserID)
            VALUES (?,       ?,        ?,       ?,          ?)
            ', $GroupID, $ArtistID, $AliasID, $Importance, $UserID
        );

        if ($DB->affected_rows()) {
            $Changed = true;
            $ArtistName = $DB->scalar('SELECT Name FROM artists_group WHERE ArtistID = ?', $ArtistID);

            Misc::write_log("Artist $ArtistID ($ArtistName) was added to the group $GroupID ($GroupName) as "
                . $ArtistTypes[$Importance].' by user '.$LoggedUser['ID'].' ('.$LoggedUser['Username'].')');
            Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "added artist $ArtistName as ".$ArtistTypes[$Importance], 0);
        }
    }
}

if ($Changed) {
    $Cache->deleteMulti(["torrents_details_$GroupID", "groups_artists_$GroupID"]);
    Torrents::update_hash($GroupID);
}

header('Location: ' . (empty($_SERVER['HTTP_REFERER']) ? "torrents.php?id=$GroupID" : $_SERVER['HTTP_REFERER']));
