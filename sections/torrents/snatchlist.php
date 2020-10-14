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

$NumResults = $DB->scalar("
    SELECT count(*) FROM xbt_snatched WHERE fid = ?
    ", $TorrentID
);
$DB->prepared_query("
    SELECT uid,
        tstamp
    FROM xbt_snatched
    WHERE fid = ?
    ORDER BY tstamp DESC
    LIMIT $Limit
    ", $TorrentID
);
$Results = $DB->to_array('uid', MYSQLI_ASSOC);
?>
<h4 class="tooltip" title="List of users that have reported a snatch to the tracker">List of Snatchers</h4>

<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_snatches', $_GET['torrentid'], $NumResults, $Page)?></div>
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
foreach ($Results as $ID=>$Data) {
    [$SnatcherID, $Timestamp] = array_values($Data);
    if ($i % 2 == 0 && $i > 0) {
?>
    </tr>
    <tr>
<?php } ?>
        <td><?=Users::format_username($SnatcherID, true, true, true, true)?></td>
        <td><?=time_diff($Timestamp)?></td>
<?php
    $i++;
}
?>
    </tr>
</table>
<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_snatches', $_GET['torrentid'], $NumResults, $Page)?></div>
<?php } ?>
