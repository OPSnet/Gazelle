<?php

/* Move a torrent from one group to another */

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
$new = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($new)) {
    error('The destination torrent group does not exist!');
}
if ($new->categoryName() !== 'Music') {
    error('Destination torrent group must be in the "Music" category.');
}

if ($old->id() === $new->id()) {
    header("Location: " . redirectUrl("torrents.php?action=edit&id=" . $old->id()));
    exit;
}

if (empty($_POST['confirm'])) {
    echo $Twig->render('torrent/confirm-move.twig', [
        'auth'    => $Viewer->auth(),
        'new'     => $new,
        'old'     => $old,
        'torrent' => $torrent
    ]);
    exit;
}

authorize();

$db = Gazelle\DB::DB();
$db->prepared_query("
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ", $new->id(), $torrent->id()
);

$log = new Gazelle\Log;
$oldId = $old->id();

if ($db->scalar("SELECT count(*) FROM torrents WHERE GroupID = ?", $old->id())) {
    $old->flush();
    $old->refresh();
} else {
    // TODO: votes etc!

    (new Gazelle\Manager\Bookmark)->merge($oldId, $new->id());
    (new Gazelle\Manager\Comment)->merge('torrents', $oldId, $new->id());
    $log->merge($oldId, $new->id());

    $old->remove($Viewer);
}

$new->flush();
$new->refresh();
$torrent->flush();
$Cache->delete_multi([
    "torrents_details_" . $oldId,
    "torrent_download_" . $torrent->id(),
]);

$log->group($new->id(), $Viewer->id(), "merged group $oldId")
    ->general("Torrent " . $torrent->id() , " was edited by " . $Viewer->label());

header('Location: ' . $new->location());
