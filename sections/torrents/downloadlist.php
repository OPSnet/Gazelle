<?php
if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid']) || !check_perms('site_view_torrent_snatchlist')) {
    error(404);
}
$TorrentID = $_GET['torrentid'];

if (!empty($_GET['page']) && is_number($_GET['page'])) {
    $Page = $_GET['page'];
    $Limit = (string)(($Page - 1) * 100) .', 100';
} else {
    $Page = 1;
    $Limit = 100;
}

$NumResults = $DB->scalar("SELECT count(*) FROM users_downloads WHERE TorrentID = ?", $TorrentID);
$DB->prepared_query("
    SELECT UserID,
        Time
    FROM users_downloads
    WHERE TorrentID = ?
    ORDER BY Time DESC
    LIMIT $Limit
    ", $TorrentID
);
$Results = $DB->to_array('UserID', MYSQLI_ASSOC);
if (empty($Results)) {
    $Snatched = [];
    $Seeding = [];
} else {
    $DB->prepared_query("
        SELECT xs.uid
        FROM xbt_snatched xs
        INNER JOIN users_downloads ud ON (ud.UserID = xs.uid)
        WHERE xs.fid = ?
        ", $TorrentID
    );
    $Snatched = $DB->to_array('uid');
    $DB->prepared_query("
        SELECT xfu.uid
        FROM xbt_files_users xfu
        INNER JOIN users_downloads ud ON (ud.UserID = xfu.uid)
        WHERE xfu.Remaining = 0 AND xfu.fid = ?
        ", $TorrentID
    );
    $Seeding = $DB->to_array('uid');
}
?>
<h4 class="tooltip" title="List of users that have clicked the [DL] button">List of Downloaders</h4>
<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_downloads', $_GET['torrentid'], $NumResults, $Page)?></div>
<?php } ?>
<table>
    <tr class="colhead_dark" style="font-weight: bold;">
        <td>User</td>
        <td>Time</td>
        <td>User</td>
        <td>Time</td>
    </tr>
    <tr>
<?php
$i = 0;
foreach ($Results as $ID => $Data) {
    [$SnatcherID, $Timestamp] = array_values($Data);

    $User = Users::format_username($SnatcherID, true, true, true, true);
    if (!array_key_exists($SnatcherID, $Snatched) && $SnatcherID != $UserID) {
        $User = '<span style="font-style: italic;">' . $User . '</span>';
        if (array_key_exists($SnatcherID, $Seeding)) {
            $User = '<strong>' . $User . '</strong>';
        }
    }
    if ($i % 2 == 0 && $i > 0) { ?>
    </tr>
    <tr>
<?php } ?>
        <td><?=$User?></td>
        <td><?=time_diff($Timestamp)?></td>
<?php
    $i++;
}
?>
    </tr>
</table>
<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_downloads', $_GET['torrentid'], $NumResults, $Page)?></div>
<?php } ?>
