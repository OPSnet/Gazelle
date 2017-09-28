<?php

$_GET['filter'] = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$Join = '';
$Where = '';
$Filter = 0;
if($_GET['filter'] === 'snatched') {
	$Join = 'JOIN xbt_snatched AS x ON x.fid = t.ID AND x.uid = '.$LoggedUser['ID'];
	$Filter = 1;
}
elseif ($_GET['filter'] === 'uploaded') {
	$Where = "AND t.UserID = {$LoggedUser['ID']}";
	$Filter = 2;
}

$DB->query("SELECT count(t.ID) as count FROM torrents AS t {$Join} WHERE t.HasLogDB='1' AND t.LogChecksum='0' {$Where}");
$row = $DB->next_record();
$total = $row['count'];
$total_str = number_format($total);
$page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page-1);
$pages = Format::get_pages($page, $total, TORRENTS_PER_PAGE);

View::show_header('Torrents with bad/missing checksum');
$DB->query("
	SELECT
		t.ID,
		t.GroupID
	FROM torrents AS t
		{$Join}
	WHERE t.HasLogDB = '1' AND t.LogChecksum = '0' {$Where}
	ORDER BY t.ID ASC");
$TorrentsInfo = $DB->to_array('ID', MYSQLI_ASSOC);
$GroupIDs = array();
foreach ($TorrentsInfo as $Torrent) {
	$GroupIDs[] = $Torrent['GroupID'];
}
$Results = (count($GroupIDs) > 0) ? Torrents::get_groups($GroupIDs) : array();
?>
	<div class="header">
		<? if ($Filter === 0) { ?>
			<h2>All torrents trumpable for bad/missing checksum</h2>
		<? } elseif ($Filter === 1) { ?>
			<h2>Torrents trumpable for bad/missing checksum that you have snatched</h2>
		<? } elseif ($Filter === 2) { ?>
			<h2>Torrents trumpable for bad/missing checksum that you have uploaded</h2>
		<? }?>

		<div class="linkbox">
			<a href="better.php" class="brackets">Back to better.php list</a>
			<? if ($Filter !== 0) { ?>
				<a href="better.php?method=checksum&amp;filter=all" class="brackets">Show all</a>
			<? } ?>
			<? if ($Filter !== 1) { ?>
				<a href="better.php?method=checksum&amp;filter=snatched" class="brackets">Show only those you have snatched</a>
			<? } ?>
			<? if ($Filter !== 2) { ?>
				<a href="better.php?method=checksum&amp;filter=uploaded" class="brackets">Show only those you have uploaded</a>
			<? } ?>
		</div>
		<div class="linkbox">
			<?=$pages?>
		</div>
	</div>
	<div class="thin box pad">
		<h3>There are <?=$total_str?> torrents remaining</h3>
		<table class="torrent_table">
			<?
			foreach ($TorrentsInfo as $TorrentID => $Info) {
				extract(Torrents::array_group($Results[$Info['GroupID']]));
				$TorrentTags = new Tags($TagList);

				if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
					unset($ExtendedArtists[2]);
					unset($ExtendedArtists[3]);
					$DisplayName = Artists::display_artists($ExtendedArtists);
				} else {
					$DisplayName = '';
				}
				$DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID#torrent$TorrentID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";
				if ($GroupYear > 0) {
					$DisplayName .= " [$GroupYear]";
				}
				if ($ReleaseType > 0) {
					$DisplayName .= ' ['.$ReleaseTypes[$ReleaseType].']';
				}

				$ExtraInfo = Torrents::torrent_info($Torrents[$TorrentID]);
				if ($ExtraInfo) {
					$DisplayName .= " - $ExtraInfo";
				}
				?>
				<tr class="torrent torrent_row<?=$GroupFlags['IsSnatched'] ? ' snatched_torrent"' : ''?>">
					<td>
				<span class="torrent_links_block">
					<a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="brackets tooltip" title="Download">DL</a>
				</span>
						<?=$DisplayName?>
						<?	if (check_perms('admin_reports')) { ?>
							<a href="better.php?method=files&amp;remove=<?=$TorrentID?>" class="brackets">X</a>
						<? 	} ?>
						<div class="tags"><?=$TorrentTags->format()?></div>
					</td>
				</tr>
				<?
			} ?>
		</table>
	</div>
<?
View::show_footer();
?>