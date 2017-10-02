<?php
View::show_header('Bonus Points Rate');

$DB->query("
SELECT
	COUNT(xfu.uid) as TotalTorrents,
	SUM(t.Size) as TotalSize,
	SUM((t.Size / (1024 * 1024 * 1024)) * (
			0.0754 + (
				LN(1 + (xs.seedtime / (24))) / (POW(GREATEST(t.Seeders, 1), 0.55))
			)
		)
	) AS TotalHourlyPoints
FROM
	xbt_files_users AS xfu
	JOIN users_info AS ui ON ui.UserID = xfu.uid
	JOIN xbt_snatched AS xs ON xs.fid = xfu.fid
	JOIN torrents AS t ON t.ID = xfu.fid
WHERE
	xfu.uid = {$LoggedUser['ID']}
	AND xfu.active = '1'
	AND xfu.remaining = 0
	AND ui.DisablePoints = '0'");

list($TotalTorrents, $TotalSize, $TotalHourlyPoints) = $DB->next_record();
$TotalTorrents = intval($TotalTorrents);
$TotalSize = intval($TotalSize);
$TotalHourlyPoints = intval($TotalHourlyPoints);
$TotalDailyPoints = $TotalHourlyPoints * 24;
$TotalWeeklyPoints = $TotalDailyPoints * 7;
// The mean number of days in a month in the Gregorian calendar,
// and then multiple that by 12
$TotalMonthlyPoints = $TotalDailyPoints * 30.436875;
$TotalYearlyPoints = $TotalDailyPoints * 365.2425;

?>
<div class="header">
	<h2>Bonus Points Shop</h2>
</div>
<div class="linkbox">
	<a href="wiki.php?action=article&id=130" class="brackets">About Bonus Points</a>
	<a href="bonus.php" class="brackets">Bonus Point Shop</a>
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
			<td><?=$TotalSize?></td>
			<td><?=number_format($TotalHourlyPoints)?></td>
			<td><?=number_format($TotalDailyPoints)?></td>
			<td><?=number_format($TotalWeeklyPoints)?></td>
			<td><?=number_format($TotalMonthlyPoints)?></td>
			<td><?=number_format($TotalYearlyPoints)?></td>
		</tr>
	</tbody>
</table>
<br />

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

$DB->query("
SELECT
	t.ID,
	t.GroupID,
	t.Size,
	t.Seeders,
	xs.seedtime,
	((t.Size / (1024 * 1024 * 1024)) * (
			0.0754 + (
				LN(1 + (xs.seedtime / (24))) / (POW(GREATEST(t.Seeders, 1), 0.55))
			)
		)
	) AS HourlyPoints
FROM
	xbt_files_users AS xfu
	JOIN users_info AS ui ON ui.UserID = xfu.uid
	JOIN xbt_snatched AS xs ON xs.fid = xfu.fid
	JOIN torrents AS t ON t.ID = xfu.fid
WHERE
	xfu.uid = {$LoggedUser['ID']}
	AND xfu.active = '1'
	AND xfu.remaining = 0
	AND ui.DisablePoints = '0'");

$GroupIDs = $DB->collect('GroupID');
$Groups = Torrents::get_groups($GroupIDs, true, true);
while(list($TorrentID, $GroupID, $Size, $Seeders, $Seedtime, $HourlyPoints) = $DB->next_record()) {
	$Size = intval($Size);
	$HourlyPoints = intval($HourlyPoints);
	$DailyPoints = $HourlyPoints * 24;
	$WeeklyPoints = $DailyPoints * 7;
	$MonthlyPoints = $DailyPoints * 30.436875;
	$YearlyPoints = $DailyPoints * 365.2425;

	extract(Torrents::array_group($Groups[$GroupID]));
	$Torrent = $Torrents[$TorrentID];

	$TorrentTags = new Tags($TagList);

	if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
		unset($ExtendedArtists[2]);
		unset($ExtendedArtists[3]);
		$DisplayName = Artists::display_artists($ExtendedArtists);
	} elseif (!empty($Artists)) {
		$DisplayName = Artists::display_artists(array(1 => $Artists));
	} else {
		$DisplayName = '';
	}
	$DisplayName .= '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$TorrentID.'" class="tooltip" title="View torrent" dir="ltr">'.$GroupName.'</a>';
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
			<td><?=$DisplayName?></td>
			<td><?=Format::get_size($Size)?></td>
			<td><?=number_format($Seeders)?></td>
			<td><?=convert_hours($Seedtime, 2)?></td>
			<td><?=number_format($HourlyPoints)?></td>
			<td><?=number_format($DailyPoints)?></td>
			<td><?=number_format($WeeklyPoints)?></td>
			<td><?=number_format($MonthlyPoints)?></td>
			<td><?=number_format($YearlyPoints)?></td>
		</tr>
<?php
}
?>

	</tbody>
</table>
<?php
$Pages = Format::get_pages($Page, $NumResults, TORRENTS_PER_PAGE);
View::show_footer();
?>