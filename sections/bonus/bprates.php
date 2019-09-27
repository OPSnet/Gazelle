<?php

$Page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$Page = max(1, $Page);
$Limit = TORRENTS_PER_PAGE;
$Offset = TORRENTS_PER_PAGE * ($Page-1);

if (!empty($_GET['userid'])) {
    if (!check_perms('admin_bp_history')) {
        error(403);
    }
    $UserID = intval($_GET['userid']);
    $User = array_merge(Users::user_stats($_GET['userid']), Users::user_info($_GET['userid']), Users::user_heavy_info($_GET['userid']));
    if (empty($User)) {
        error(404);
    }
}
else {
    $UserID = $LoggedUser['ID'];
    $User = $LoggedUser;
}

$Title = ($UserID === $LoggedUser['ID']) ? 'Your Bonus Points Rate' : "{$User['Username']}'s Bonus Point Rate";
View::show_header($Title);

$DB->prepared_query("
SELECT
    COUNT(xfu.uid) as TotalTorrents,
    SUM(t.Size) as TotalSize,
    SUM(IFNULL((t.Size / (1024 * 1024 * 1024)) * (
        0.0433 + (
            (0.07 * LN(1 + (xfh.seedtime / (24)))) / (POW(GREATEST(tls.Seeders, 1), 0.35))
        )
    ), 0)) AS TotalHourlyPoints
FROM (
    SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
) AS xfu
INNER JOIN xbt_files_history AS xfh ON (xfh.uid = xfu.uid AND xfh.fid = xfu.fid)
INNER JOIN torrents AS t ON (t.ID = xfu.fid)
INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
WHERE
    xfu.uid = ?", $UserID, $UserID);

list($TotalTorrents, $TotalSize, $TotalHourlyPoints) = $DB->next_record();
$TotalTorrents = intval($TotalTorrents);
$TotalSize = floatval($TotalSize);
$TotalHourlyPoints = floatval($TotalHourlyPoints);
$TotalDailyPoints = $TotalHourlyPoints * 24;
$TotalWeeklyPoints = $TotalDailyPoints * 7;
// The mean number of days in a month in the Gregorian calendar,
// and then multiple that by 12
$TotalMonthlyPoints = $TotalDailyPoints * 30.436875;
$TotalYearlyPoints = $TotalDailyPoints * 365.2425;

$Pages = Format::get_pages($Page, $TotalTorrents, TORRENTS_PER_PAGE);

?>
<div class="header">
    <h2><?=$Title?></h2>
    <h3>Points: <?=number_format($User['BonusPoints'])?></h3>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php" class="brackets">Bonus Point Shop</a>
    <a href="bonus.php?action=history<?= check_perms('admin_bp_history') && $UserID != G::$LoggedUser['ID'] ? "&amp;userid=$UserID" : '' ?>" class="brackets">History</a>
</div>
<table>
    <thead>
        <tr class="colhead">
            <td>Total Torrents</td>
            <td>Size</td>
            <td>BP/hour</td>
            <td>BP/day</td>
            <td>BP/week</td>
            <td>BP/month</td>
            <td>BP/year</td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?=$TotalTorrents?></td>
            <td><?=Format::get_size($TotalSize)?></td>
            <td><?=number_format($TotalHourlyPoints, 2)?></td>
            <td><?=number_format($TotalDailyPoints, 2)?></td>
            <td><?=number_format($TotalWeeklyPoints, 2)?></td>
            <td><?=number_format($TotalMonthlyPoints, 2)?></td>
            <td><?=number_format($TotalYearlyPoints, 2)?></td>
        </tr>
    </tbody>
</table>
<br />

<div class="linkbox">
    <?=$Pages?>
</div>
<table>
    <thead>
    <tr class="colhead">
        <td>Torrent</td>
        <td>Size</td>
        <td>Seeders</td>
        <td>Seedtime</td>
        <td>BP/hour</td>
        <td>BP/day</td>
        <td>BP/week</td>
        <td>BP/month</td>
        <td>BP/year</td>
    </tr>
    </thead>
    <tbody>
<?php

if ($TotalTorrents > 0) {
    $DB->prepared_query("
    SELECT
        t.ID,
        t.GroupID,
        t.Size,
        t.Format,
        t.Encoding,
        t.HasLog,
        t.HasLogDB,
        t.HasCue,
        t.LogScore,
        t.LogChecksum,
        t.Media,
        t.Scene,
        t.RemasterYear,
        t.RemasterTitle,
        GREATEST(tls.Seeders, 1) AS Seeders,
        xfh.seedtime AS Seedtime,
        ((t.Size / (1024 * 1024 * 1024)) * (
            0.0433 + (
                (0.07 * LN(1 + (xfh.seedtime / (24)))) / (POW(GREATEST(tls.Seeders, 1), 0.35))
            )
        )) AS HourlyPoints
    FROM (
        SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?
    ) AS xfu
    INNER JOIN xbt_files_history AS xfh ON xfh.uid = xfu.uid AND xfh.fid = xfu.fid
    INNER JOIN torrents AS t ON t.ID = xfu.fid
    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
    WHERE
        xfu.uid = ?
    LIMIT ?
    OFFSET ?", $UserID, $UserID, $Limit, $Offset);

    $GroupIDs = $DB->collect('GroupID');
    $Groups = Torrents::get_groups($GroupIDs, true, true, false);
    while ($Torrent = $DB->next_record(MYSQLI_ASSOC)) {
        // list($TorrentID, $GroupID, $Size, $Format, $Encoding, $HasLog, $HasLogDB, $HasCue, $LogScore, $LogChecksum, $Media, $Scene, $Seeders, $Seedtime, $HourlyPoints)
        $Size = intval($Torrent['Size']);
        $Seeders = intval($Torrent['Seeders']);
        $HourlyPoints = floatval($Torrent['HourlyPoints']);
        $DailyPoints = $HourlyPoints * 24;
        $WeeklyPoints = $DailyPoints * 7;
        $MonthlyPoints = $DailyPoints * 30.436875;
        $YearlyPoints = $DailyPoints * 365.2425;

        $GroupYear = $Groups[$Torrent['GroupID']]['Year'];
        $Torrents = isset($Groups[$Torrent['GroupID']]['Torrents']) ? $Groups[$Torrent['GroupID']]['Torrents'] : [];
        $Artists = $Groups[$Torrent['GroupID']]['Artists'];
        $ExtendedArtists = $Groups[$Torrent['GroupID']]['ExtendedArtists'];

        if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
            unset($ExtendedArtists[2]);
            unset($ExtendedArtists[3]);
            $DisplayName = Artists::display_artists($ExtendedArtists);
        } elseif (!empty($Artists)) {
            $DisplayName = Artists::display_artists([1 => $Artists]);
        } else {
            $DisplayName = '';
        }
        $DisplayName .= '<a href="torrents.php?id=' . $Torrent['GroupID'] . '&amp;torrentid=' . $Torrent['ID'] . '" class="tooltip" title="View torrent" dir="ltr">' . $Groups[$Torrent['GroupID']]['Name'] . '</a>';
        if ($GroupYear > 0) {
            $DisplayName .= " [$GroupYear]";
        }
        if ($GroupVanityHouse) {
            $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }

        $ExtraInfo = Torrents::torrent_info($Torrent);
        if ($ExtraInfo) {
            $DisplayName .= " - $ExtraInfo";
        }
?>
    <tr>
        <td><?= $DisplayName ?></td>
        <td><?= Format::get_size($Torrent['Size']) ?></td>
        <td><?= number_format($Seeders) ?></td>
        <td><?= convert_hours($Torrent['Seedtime'], 2) ?></td>
        <td><?= number_format($HourlyPoints, 2) ?></td>
        <td><?= number_format($DailyPoints, 2) ?></td>
        <td><?= number_format($WeeklyPoints, 2) ?></td>
        <td><?= number_format($MonthlyPoints, 2) ?></td>
        <td><?= number_format($YearlyPoints, 2) ?></td>
    </tr>
<?php
    }
}
else {
?>
    <tr>
        <td colspan="9" style="text-align:center;">No torrents being seeded currently</td>
    </tr>
<?php
}
?>

    </tbody>
</table>
<div class="linkbox">
    <?=$Pages?>
</div>
<?php
View::show_footer();
