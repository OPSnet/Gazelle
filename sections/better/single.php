<?php
if (($Results = $Cache->get_value('better_single_groupids')) === false) {
    $DB->query("
        SELECT
            t.ID AS TorrentID,
            t.GroupID AS GroupID
        FROM xbt_files_users AS x
            JOIN torrents AS t ON t.ID=x.fid
        WHERE t.Format='FLAC'
        GROUP BY x.fid
        HAVING COUNT(x.uid) = 1
        ORDER BY t.LogScore DESC, t.Time ASC
        LIMIT 30");

    $Results = $DB->to_pair('GroupID', 'TorrentID', false);
    $Cache->cache_value('better_single_groupids', $Results, 30 * 60);
}

$Groups = Torrents::get_groups(array_keys($Results));

View::show_header('Single seeder FLACs');
?>
<br />
<div class="thin">
    <h2>Single Seeded</h2>
    <div class="linkbox">
        <a class="brackets" href="better.php?method=transcode">Transcodes</a>
        <a class="brackets" href="better.php?method=missing">Missing</a>
        <a class="brackets" href="better.php?method=single">Single Seeded</a>
    </div>
    <div class="box pad">
        <table width="100%" class="torrent_table">
            <tr class="colhead">
                <td>Torrent</td>
            </tr>
<?php
foreach ($Results as $GroupID => $FlacID) {
    if (!isset($Groups[$GroupID])) {
        continue;
    }
    $Group = $Groups[$GroupID];

    if (!empty($Group['ExtendedArtists'][1]) || !empty($Group['ExtendedArtists'][4]) || !empty($Group['ExtendedArtists'][5]) || !empty($Group['ExtendedArtists'][6])) {
        unset($Group['ExtendedArtists'][2]);
        unset($Group['ExtendedArtists'][3]);
        $DisplayName = Artists::display_artists($Group['ExtendedArtists']);
    } else {
        $DisplayName = '';
    }

    $DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$FlacID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">" . $Group['GroupName'] . '</a>';
    if ($Group['GroupYear'] > 0) {
        $DisplayName .= ' [' . $Group['GroupYear'] . ']';
    }
    if ($Group['ReleaseType'] > 0) {
        $DisplayName .= ' [' . $ReleaseTypes[$Group['ReleaseType']] . ']';
    }

    $ExtraInfo = Torrents::torrent_info($Torrents[$FlacID]);
    if ($ExtraInfo) {
        $DisplayName .= ' - ' . $ExtraInfo;
    }
    $TorrentTags = new Tags($Group['TagList']);
?>
            <tr class="torrent torrent_row<?=$Torrents[$FlacID]['IsSnatched'] ? ' snatched_torrent' : ''?>">
                <td>
                    <span class="torrent_links_block">
                        <a href="torrents.php?action=download&amp;id=<?=$FlacID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download" class="brackets tooltip">DL</a>
                    </span>
                    <?=$DisplayName?>
                    <div class="tags"><?=$TorrentTags->format()?></div>
                </td>
            </tr>
<?php
} ?>
        </table>
    </div>
</div>
<?php
View::show_footer();
