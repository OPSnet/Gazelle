<?php
authorize();

$UserID = $Viewer->id();
$GroupID = $_POST['groupid'];
$ArtistRoles = $_POST['importance'];
$AliasNames = $_POST['aliasname'];

$GroupName = $DB->scalar('SELECT Name FROM torrents_group WHERE ID = ?', (int)$GroupID);
if (!$GroupName) {
    error(404);
}

$Changed = false;

$artistMan = new \Gazelle\Manager\Artist;
$artistMan->setGroupId($GroupID)->setUserId($UserID);
for ($i = 0; $i < count($AliasNames); $i++) {
    $AliasName = \Gazelle\Artist::sanitize($AliasNames[$i]);
    $role = $ArtistRoles[$i];
    if (!in_array($role, ['1', '2', '3', '4', '5', '6', '7', '8'])) {
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
            [$ArtistID, $AliasID] = $artistMan->create($AliasName);
        }
        $artistMan->addToGroup($ArtistID, $AliasID, $role);

        if ($DB->affected_rows()) {
            $ArtistName = $DB->scalar('SELECT Name FROM artists_group WHERE ArtistID = ?', $ArtistID);
            (new Gazelle\Log)->group($GroupID, $Viewer->id(), "added artist $ArtistName as ".$ArtistTypes[$role])
                ->general("Artist $ArtistID ($ArtistName) was added to the group $GroupID ($GroupName) as "
                    . $ArtistTypes[$role].' by user '.$Viewer->id().' ('.$Viewer->username().')'
                );
            $Changed = true;
        }
    }
}

if ($Changed) {
    (new \Gazelle\Manager\TGroup)->refresh($GroupID);
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id=$GroupID");
