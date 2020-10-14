<?php
if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
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
    SELECT count(*)
    FROM xbt_files_users AS xu
    INNER JOIN users_main AS um ON (um.ID = xu.uid)
    INNER JOIN torrents AS t ON (t.ID = xu.fid)
    WHERE xu.fid = ?
        AND um.Visible = '1'
    ", $TorrentID
);
$Result = $DB->prepared_query("
    SELECT
        xu.uid,
        t.Size,
        xu.active,
        xu.connectable,
        xu.uploaded,
        xu.remaining,
        xu.useragent
    FROM xbt_files_users AS xu
    INNER JOIN users_main AS um ON (um.ID = xu.uid)
    INNER JOIN torrents AS t ON (t.ID = xu.fid)
    WHERE xu.fid = ?
        AND um.Visible = '1'
    ORDER BY xu.uid = ? DESC, xu.uploaded DESC
    LIMIT $Limit
    ", $TorrentID, $LoggedUser[ID]
);
?>
<h4>Peer List</h4>
<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_peers', $_GET['torrentid'], $NumResults, $Page)?></div>
<?php } ?>

<table>
    <tr class="colhead_dark" style="font-weight: bold;">
        <td>User</td>
        <td>Active</td>
        <td>Connectable</td>
        <td class="number_column">Up (this session)</td>
        <td class="number_column">%</td>
        <td>Client</td>
    </tr>
<?php
while ([$PeerUserID, $Size, $Active, $Connectable, $Uploaded, $Remaining, $UserAgent] = $DB->next_record()) {
?>
    <tr>
<?php if (check_perms('users_mod') || $PeerUserID == $LoggedUser['ID']) { ?>
        <td><?=Users::format_username($PeerUserID, false, false, false)?></td>
<?php } else { ?>
        <td>Peer</td>
<?php } ?>
        <td><?=($Active) ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
        <td><?= ($Connectable) ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>' ?></td>
        <td class="number_column"><?=Format::get_size($Uploaded) ?></td>
        <td class="number_column"><?=number_format(($Size - $Remaining) / $Size * 100, 2)?></td>
        <td><?=display_str($UserAgent)?></td>
    </tr>
<?php } ?>
</table>
<?php if ($NumResults > 100) { ?>
<div class="linkbox"><?=js_pages('show_peers', $_GET['torrentid'], $NumResults, $Page)?></div>
<?php } ?>
