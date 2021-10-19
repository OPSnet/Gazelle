<?php
/***************************************************************
* This page handles the backend of the "new group" function
* which splits a torrent off into a new group.
****************************************************************/

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$OldGroupID = (int)$_POST['oldgroupid'];
$TorrentID  = (int)$_POST['torrentid'];
$ArtistName = trim($_POST['artist']);
$Title      = trim($_POST['title']);
$Year       = (int)$_POST['year'];
if (!$OldGroupID || !$TorrentID || !$Year || empty($Title) || empty($ArtistName)) {
    error(0);
}

// double check
if (empty($_POST['confirm'])) {
    View::show_header('Split new group');
    echo $Twig->render('torrent/confirm-split.twig', [
        'artist'     => $_POST['artist'],
        'auth'       => $Viewer->auth(),
        'group_id'   => $OldGroupID,
        'torrent_id' => $TorrentID,
        'title'      => $_POST['title'],
        'year'       => $_POST['year'],
    ]);
    View::show_footer();
    exit;
}

$DB->prepared_query("
    INSERT INTO torrents_group
           (Name, Year, CategoryID, WikiBody, WikiImage)
    VALUES (?,    ?,    1,          '',       '')
    ", $Title, $Year
);
$GroupID = $DB->inserted_id();

$tgroupMan = new \Gazelle\Manager\TGroup;
$tgroup = $tgroupMan->findById($GroupID);
$tgroup->addArtists($Viewer, [ARTIST_MAIN], [$ArtistName]);

$DB->prepared_query('
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ', $GroupID, $TorrentID
);

// Update or remove previous group, depending on whether there is anything left
$tgroupMan->refresh($GroupID);
if ($DB->scalar('SELECT 1 FROM torrents WHERE GroupID = ?', $OldGroupID)) {
    $tgroupMan->refresh($OldGroupID);
} else {
    Torrents::delete_group($OldGroupID, $Viewer);
}

$Cache->delete_value("torrent_download_$TorrentID");

(new Gazelle\Log)->general("Torrent $TorrentID was split out from group $OldGroupID to $GroupId by " . $Viewer->label());

header("Location: torrents.php?id=$GroupID");
