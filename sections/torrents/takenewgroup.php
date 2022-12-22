<?php
/***************************************************************
* This page handles the backend of the "new group" function
* which splits a torrent off into a new group.
****************************************************************/

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('Torrent does not exist!');
}

$tgMan = new Gazelle\Manager\TGroup;
$old = $tgMan->findById((int)($_POST['oldgroupid'] ?? 0));
if (is_null($old)) {
    error('The source torrent group does not exist!');
}

$ArtistName = trim($_POST['artist']);
$Title      = trim($_POST['title']);
$Year       = (int)$_POST['year'];
if (!$Year || empty($Title) || empty($ArtistName)) {
    error(0);
}

// double check
if (empty($_POST['confirm'])) {
    echo $Twig->render('torrent/confirm-split.twig', [
        'artist'     => $_POST['artist'],
        'auth'       => $Viewer->auth(),
        'group_id'   => $old->id(),
        'torrent_id' => $torrent->id(),
        'title'      => $_POST['title'],
        'year'       => $_POST['year'],
    ]);
    exit;
}

$DB->prepared_query("
    INSERT INTO torrents_group
           (Name, Year, CategoryID, WikiBody, WikiImage)
    VALUES (?,    ?,    1,          '',       '')
    ", $Title, $Year
);
$new = $tgMan->findById($DB->inserted_id());

$new->addArtists($Viewer, [ARTIST_MAIN], [$ArtistName]);

$DB->prepared_query('
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ', $new->id(), $torrent->id()
);

$log = new Gazelle\Log;
$oldId = $old->id();

// Update or remove previous group, depending on whether there is anything left
if ($DB->scalar('SELECT 1 FROM torrents WHERE GroupID = ?', $oldId)) {
    $old->flush();
    $old->refresh();
} else {
    // TODO: votes etc!

    (new Gazelle\Manager\Bookmark)->merge($oldId, $new->id());
    (new Gazelle\Manager\Comment)->merge('torrents', $oldId, $new->id());
    $log->merge($oldId, $new->id());

    $old->remove($Viewer, $log);
}

$new->flush();
$new->refresh();
$torrent->flush();
$Cache->deleteMulti([
    "torrents_details_" . $oldId,
    "torrent_download_" . $torrent->id(),
]);

$log->group($new->id(), $Viewer->id(), "split from group $oldId")
    ->general("Torrent " . $torrent->id() . " was split out from group $oldId to " . $new->id() . " by " . $Viewer->label());

header('Location: ' . $new->location());
