<?php
$TorrentID = (int)$_GET['torrentid'];

$DB->prepared_query('
    SELECT tls.last_action, t.LastReseedRequest, t.UserID, t.Time, t.GroupID
    FROM torrents AS t
    INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
    WHERE ID = ?
    ', $TorrentID
);
list($LastActive, $LastReseedRequest, $UploaderID, $UploadedTime, $GroupID) = $DB->next_record();

if (!check_perms('users_mod')) {
    if (time() - strtotime($LastReseedRequest) < 864000) {
        error('There was already a re-seed request for this torrent within the past 10 days.');
    }
    if (time() - strtotime($LastActive) < 345678) {
        error(403);
    }
}

$DB->prepared_query('
    UPDATE torrents
    SET LastReseedRequest = now()
    WHERE ID = ?
    ', $TorrentID
);

$Groups = Torrents::get_groups([$GroupID]);
$Group = $Groups[$GroupID];

$Name = Artists::display_artists(['1' => $Group['Artists']], false, true);
$Name .= $Group['Name'];

$DB->prepared_query('
    SELECT s.uid AS id, MAX(s.tstamp) AS tstamp
    FROM xbt_snatched as s
    INNER JOIN users_main as u ON (s.uid = u.ID)
    WHERE s.fid = ?
    AND u.Enabled = ?
    GROUP BY s.uid
    ORDER BY tstamp DESC
    LIMIT 100
    ', $TorrentID, '1'
);

$usersToNotify = [];
if ($DB->has_results()) {
    $Users = $DB->to_array();
    foreach ($Users as $User) {
        $usersToNotify[$User['id']] = ["snatched", $User['tstamp']];
    }
}

$usersToNotify[$UploaderID] = ["uploaded", strtotime($UploadedTime)];

$userMan = new Gazelle\Manager\User;
foreach ($usersToNotify as $UserID => $info) {
    $user = new Gazelle\User($UserID);
    $Username = $user->username();
    [$action, $TimeStamp] = $info;

    $Request = "Hi $Username,

The user [url=user.php?id=" . $Viewer->id() . "]" . $Viewer->username() . "[/url] has requested a re-seed for the torrent [url=torrents.php?id=$GroupID&torrentid=$TorrentID]{$Name}[/url], which you ".$action." on ".date('M d Y', $TimeStamp).". The torrent is now un-seeded, and we need your help to resurrect it!

The exact process for re-seeding a torrent is slightly different for each client, but the concept is the same. The idea is to download the torrent file and open it in your client, and point your client to the location where the data files are, then initiate a hash check.

Thanks!";

    $userMan->sendPM($UserID, 0, "Re-seed request for torrent $Name", $Request);
}

$NumUsers = count($usersToNotify);

View::show_header('Reseed ' . display_str($Name));
?>
<div class="thin">
    <div class="header">
        <h2>Successfully sent re-seed request</h2>
    </div>
    <div class="box pad thin">
        <p>Successfully sent re-seed request for torrent <a href="torrents.php?id=<?=$GroupID?>&torrentid=<?=$TorrentID?>"><?=display_str($Name)?></a> to <?=$NumUsers?> user<?= plural($NumUsers) ?>.</p>
    </div>
</div>
<?php
View::show_footer();
