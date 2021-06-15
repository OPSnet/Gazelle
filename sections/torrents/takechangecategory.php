<?php
/***************************************************************
* Temp handler for changing the category for a single torrent.
****************************************************************/

authorize();
if (!check_perms('users_mod')) {
    error(403);
}

$OldGroupID = (int)$_POST['oldgroupid'];
$TorrentID = (int)$_POST['torrentid'];
$Title = trim($_POST['title']);
$OldCategoryID = (int)$_POST['oldcategoryid'];
$NewCategoryID = (int)$_POST['newcategoryid'];
if (!$OldGroupID || !$NewCategoryID || !$TorrentID || empty($Title)) {
    error(0);
}

switch (CATEGORY[$NewCategoryID - 1]) {
    case 'Music':
        $ArtistName = trim($_POST['artist']);
        $Year = (int)$_POST['year'];
        $ReleaseType = (int)$_POST['releasetype'];
        if (empty($ArtistName) || !$Year || !$ReleaseType) {
            error(0);
        }
        [$ArtistID, $AliasID, $Redirect, $ArtistName] = $DB->row('
            SELECT ArtistID, AliasID, Redirect, Name
            FROM artists_alias
            WHERE Name LIKE ?
            ', $ArtistName
        );
        $artistMan = new \Gazelle\Manager\Artist;
        if (!$DB->has_results()) {
            [$ArtistID, $AliasID] = $artistMan->create($AliasName);
            $Redirect = 0;
        } else {
            [$ArtistID, $AliasID, $Redirect, $ArtistName] = $DB->next_record();
            if ($Redirect) {
                $AliasID = $ArtistID;
            }
        }

        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, ReleaseType, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,           1,         '',       '')
            ", $Title, $Year, $ReleaseType
        );
        $GroupID = $DB->inserted_id();

        $artistMan->setGroupId($GroupID)->setUserId($Viewer->id())
            ->addToGroup($ArtistID, $AliasID, 1);
        break;
    case 'Audiobooks':
    case 'Comedy':
        $Year = (int)$_POST['year'];
        if (!$Year) {
            error(0);
        }
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,          '',       ''))
            ", $Title, $Year, $NewCategoryID
        );
        $GroupID = $DB->inserted_id();
        break;
    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,          '',       '')
            ", $Title, $NewCategoryID
        );
        $GroupID = $DB->inserted_id();
        break;
}

$DB->prepared_query('
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ', $GroupID, $TorrentID
);

// Delete old group if needed
$tgroupMan = new \Gazelle\Manager\TGroup;
if ($DB->scalar('SELECT ID FROM torrents WHERE GroupID = ?', $OldGroupID)) {
    $torMan->refresh($OldGroupID);
} else {
    // TODO: votes etc.
    $DB->prepared_query("
        UPDATE comments SET
            PageID = ?
        WHERE Page = 'torrents' AND PageID = ?
        ", $GroupID, $OldGroupID
    );
    Torrents::delete_group($OldGroupID);
    $Cache->delete_value("torrent_comments_{$GroupID}_catalogue_0");
}

$DB->prepared_query('
    UPDATE group_log SET
        GroupID = ?
    WHERE GroupID = ?
    ', $GroupID, $OldGroupID
);

$torMan->refresh($GroupID);

$Cache->delete_value("torrent_download_$TorrentID");

(new Gazelle\Log)->group($GroupID, $Viewer->id(), "category changed from $OldCategoryID to $NewCategoryID, merged from group $OldGroupID")
    ->general("Torrent $TorrentID was changed to category $NewCategoryID by " . $Viewer->username());

header("Location: torrents.php?id=$GroupID");
