<?php
authorize();

$TorrentID = (int)$_POST['torrentid'];
if (!$TorrentID) {
    error(404);
}

if ($Cache->get_value("torrent_{$TorrentID}_lock")) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !check_perms('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}

[$UserID, $GroupID, $Size, $InfoHash, $Name, $Year, $ArtistName, $Media, $Format, $Encoding,
    $HasCue, $HasLogDB, $LogScore, $Remastered, $RemasterTitle, $RemasterYear] = $DB->row('
    SELECT
        t.UserID,
        t.GroupID,
        t.Size,
        t.info_hash,
        tg.Name,
        tg.Year,
        ag.Name,
        t.Media,
        t.Format,
        t.Encoding,
        t.HasCue,
        t.HasLogDB,
        t.LogScore,
        t.Remastered,
        t.RemasterTitle,
        t.RemasterYear
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
    LEFT JOIN artists_group AS ag ON (ag.ArtistID = ta.ArtistID)
    WHERE t.ID = ?
    ', $TorrentID
);

if ($LoggedUser['ID'] != $UserID && !check_perms('torrents_delete')) {
    error(403);
}

if (empty($ArtistName)) {
    $RawName = $Name;
}
elseif ($ArtistName == 'Various Artists') {
    $RawName = "Various Artists - $Name";
}
else {
    $RawName = "$ArtistName - $Name";
}

if ($ArtistName) {
    // more pretzel logic
    $Name = "$ArtistName - $Name";
}

$RawName .= ($Year ? " ($Year)" : '')
    . ($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '')
    . Reports::format_reports_remaster_info($Remastered, $RemasterTitle, $RemasterYear)
    . ($HasCue ? ' (Cue)' : '')
    . ($HasLogDB ? " (Log: {$LogScore}%)" : '')
    . ' (' . Format::get_size($Size) . ')';

$Err = Torrents::delete_torrent($TorrentID, $GroupID);
if ($Err) {
    error($Err);
}

$InfoHash = unpack('H*', $InfoHash);
$sizeMB_hash = "$sizeMB " . strtoupper($InfoHash[1]);
$reason = trim($_POST['reason'] . ' ' . $_POST['extra']);
$Log = "Torrent $TorrentID ($Name) ($sizeMB_hash was deleted by {$LoggedUser['Username']}: $reason";
Torrents::send_pm($TorrentID, $UserID, $RawName, $Log, 0, G::$LoggedUser['ID'] != $UserID);
(new Gazelle\Log)
    ->torrent($GroupID, $TorrentID, $LoggedUser['ID'], "deleted torrent ($sizeMB_hash) for reason: $reason")
    ->general($Log);
View::show_header('Torrent deleted');
?>
<div class="thin">
    <h3>Torrent was successfully deleted.</h3>
</div>
<?php
View::show_footer();
