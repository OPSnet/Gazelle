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

    if (!in_array($Importance, ['1', '2', '3', '4', '5', '6', '7', '8'])) {
        break;
    }

    if (strlen($AliasName) > 0) {
        $DB->prepared_query('
            SELECT AliasID, ArtistID, Redirect, Name
            FROM artists_alias
            WHERE Name = ?
            ', $AliasName
        );
        while ([$AliasID, $ArtistID, $Redirect, $FoundAliasName] = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($AliasName, $FoundAliasName)) {
                if ($Redirect) {
                    $AliasID = $Redirect;
                }
                break;
            }
        }
        if (!$AliasID) {
            [$ArtistID, $AliasID] = $ArtistManager->createArtist($AliasName);
        }
        $ArtistName = $DB->scalar('SELECT Name FROM artists_group WHERE ArtistID = ?', $ArtistID);

        $DB->prepared_query('
            INSERT IGNORE INTO torrents_artists
                   (GroupID, ArtistID, AliasID, Importance, UserID)
            VALUES (?,       ?,        ?,       ?,          ?)
            ', $GroupID, $ArtistID, $AliasID, $Importance, $UserID
        );

        if ($DB->affected_rows()) {
            (new Gazelle\Log)->group($GroupID, $LoggedUser['ID'], "added artist $ArtistName as ".$ArtistTypes[$Importance])
                ->general("Artist $ArtistID ($ArtistName) was added to the group $GroupID ($GroupName) as "
                    . $ArtistTypes[$Importance].' by user '.$LoggedUser['ID'].' ('.$LoggedUser['Username'].')'
                );
            $Changed = true;
        }
    }
}

if ($Changed) {
    $Cache->deleteMulti(["torrents_details_$GroupID", "groups_artists_$GroupID"]);
    Torrents::update_hash($GroupID);
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id=$GroupID");
