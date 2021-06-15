<?php
authorize();

$t = (new Gazelle\Manager\Torrent)->findById((int)$_POST['torrentid']);
if (is_null($t)) {
    error(404);
}
$t->setViewerId($LoggedUser['ID']);
$tgroup  = $t->group();
$group   = $tgroup->info();
$torrent = $t->info();
$torrentId = $t->id();

if ($LoggedUser['ID'] != $torrent['UserID'] && !check_perms('torrents_delete')) {
    error(403);
}

if ($Cache->get_value("torrent_{$torrentId}_lock")) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !check_perms('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}

$labelMan = new Gazelle\Manager\TorrentLabel;
$labelMan->showMedia(true)
    ->showEdition(true)
    ->load($torrent);

$name = $group['Name'] . " [" . $labelMan->release() . '] (' . $labelMan->edition() . ')';
$artistName = $tgroup->artistName();
if ($artistName) {
    $name = "$artistName - $name";
}

$reason = trim($_POST['reason']) . ' ' . trim($_POST['extra']);
[$success, $message] = $t->remove($LoggedUser['ID'], $reason);
if (!$success) {
    error($message);
}

(new Gazelle\Manager\User)->sendRemovalPM(
    $torrentId,
    $torrent['UserID'],
    $name,
    "Torrent $torrentId $name ("
        . number_format($torrent['Size'] / (1024 * 1024), 2) . ' MiB '
        . strtoupper($torrent['info_hash'])
        . ") was deleted by {$LoggedUser['Username']}: $reason",
    0,
    $LoggedUser['ID'] != $torrent['UserID']
);
View::show_header('Torrent deleted');
?>
<div class="thin">
    <h3>Torrent <?= $name ?> was successfully deleted.</h3>
</div>
<?php
View::show_footer();
