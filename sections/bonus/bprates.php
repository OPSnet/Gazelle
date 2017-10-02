<?php

$Page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$Page = max(1, $Page);
$Limit = TORRENTS_PER_PAGE;
$Offset = TORRENTS_PER_PAGE * ($Page-1);

View::show_header('Bonus Points Rate');

if (empty($_GET['id']) || !check_perms('users_mod')) {
	$UserID = $LoggedUser['ID'];
	$User = $LoggedUser;
}
else {
	$UserID = intval($_GET['id']);
	$User = array_merge(Users::user_stats($_GET['id']), Users::user_heavy_info($_GET['id']));
}

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
	JOIN xbt_snatched AS xs ON xs.fid = xfu.fid AND xs.uid = xfu.uid
	JOIN torrents AS t ON t.ID = xfu.fid
WHERE
	xfu.uid = {$UserID}
	AND xfu.active = '1'
	AND xfu.remaining = 0
	AND ui.DisablePoints = '0'");

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

?>
<div class="header">
	<h2>Bonus Points Rates</h2>
	<h3>Points: <?=number_format($User['BonusPoints'])?></h3>
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
			<td><?=number_format($TotalHourlyPoints, 2)?></td>
			<td><?=number_format($TotalDailyPoints, 2)?></td>
			<td><?=number_format($TotalWeeklyPoints, 2)?></td>
			<td><?=number_format($TotalMonthlyPoints, 2)?></td>
			<td><?=number_format($TotalYearlyPoints, 2)?></td>
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
	COUNT(*) as count
FROM
	xbt_files_users AS xfu
	JOIN xbt_snatched AS xs ON xs.fid = xfu.fid AND xs.uid = xfu.uid
WHERE
	xfu.uid = {$UserID}
	AND xfu.active = '1'
	AND xfu.remaining = 0");

list($NumResults) = $DB->next_record();

$DB->query("
SELECT
	t.ID,
	t.GroupID,
	t.Size,
	GREATEST(t.Seeders, 1),
	xs.seedtime,
	((t.Size / (1024 * 1024 * 1024)) * (
			0.0754 + (
				LN(1 + (xs.seedtime / (24))) / (POW(GREATEST(t.Seeders, 1), 0.55))
			)
		)
	) AS HourlyPoints
FROM
	xbt_files_users AS xfu
	JOIN xbt_snatched AS xs ON xs.fid = xfu.fid AND xs.uid = xfu.uid
	JOIN torrents AS t ON t.ID = xfu.fid
WHERE
	xfu.uid = {$UserID}
	AND xfu.active = '1'
	AND xfu.remaining = 0
LIMIT {$Limit}
OFFSET {$Offset}");

$GroupIDs = $DB->collect('GroupID');
$Groups = Torrents::get_groups($GroupIDs, true, true);
while(list($TorrentID, $GroupID, $Size, $Seeders, $Seedtime, $HourlyPoints) = $DB->next_record()) {
	$Size = intval($Size);
	$Seeders = intval($Seeders);
	$HourlyPoints = floatval($HourlyPoints);
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
			<td><?=number_format($HourlyPoints, 2)?></td>
			<td><?=number_format($DailyPoints, 2)?></td>
			<td><?=number_format($WeeklyPoints, 2)?></td>
			<td><?=number_format($MonthlyPoints, 2)?></td>
			<td><?=number_format($YearlyPoints, 2)?></td>
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