<?php
$GroupID = (int)$_GET['id'];
if (!$GroupID) {
    error(404);
}

$Groups = Torrents::get_groups([$GroupID], true, true, false);
$Title = empty($Groups[$GroupID])
    ? "Group $GroupID"
    : Artists::display_artists($Groups[$GroupID]['ExtendedArtists'])
        . '<a href="torrents.php?id=' . $GroupID . '">' . $Groups[$GroupID]['Name'] . '</a>';
View::show_header("History for Group $GroupID");
?>
<div class="thin">
    <div class="header">
        <h2>History for <?=$Title?></h2>
    </div>
    <table>
        <tr class="colhead">
            <td>Date</td>
            <td>Torrent</td>
            <td>User</td>
            <td>Info</td>
        </tr>
<?php
$DB->prepared_query("
    SELECT TorrentID, UserID, Info, Time
    FROM group_log
    WHERE GroupID = ?
    ORDER BY Time DESC
    ", $GroupID
);
while ([$TorrentID, $UserID, $Info, $Time] = $DB->next_record()) {
?>
        <tr class="rowa">
            <td><?=$Time?></td>

<?php if (!$TorrentID) { ?>
            <td></td>
<?php
    } else {
        [$Media, $Format, $Encoding] = $DB->row("
            SELECT Media, Format, Encoding
            FROM torrents
            WHERE ID = ?
            ", $TorrentID
        );
        if (is_null($Media)) {
?>
            <td><a href="torrents.php?torrentid=<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)</td>
<?php   } elseif ($Media == '') { ?>
            <td><a href="torrents.php?torrentid=<?=$TorrentID?>"><?=$TorrentID?></a></td>
<?php   } else { ?>
            <td><a href="torrents.php?torrentid=<?=$TorrentID?>"><?=$TorrentID?></a> (<?=$Format?>/<?=$Encoding?>/<?=$Media?>)</td>
<?php
        }
    }
?>
        <td><?=Users::format_username($UserID, false, false, false)?></td>
        <td><?=$Info?></td>
    </tr>
<?php } /* foreach */ ?>
    </table>
</div>
<?php
View::show_footer();
