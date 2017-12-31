<?php
enforce_login();
if (isset($_POST['leaderboard']) && is_number($_POST['leaderboard'])) {
	$Contest = Contest::get_contest(intval($_POST['leaderboard']));
}
elseif (isset($_GET['id']) && is_number($_GET['id'])) {
	$Contest = Contest::get_contest(intval($_GET['id']));
}
else {
	$Contest = Contest::get_current_contest();
}

if (!empty($Contest)) {
	$Leaderboard = Contest::get_leaderboard($Contest['ID']);
	View::show_header($Contest['Name'].' Leaderboard');
}
else {
	View::show_header('Leaderboard');
}

if ($Contest['Banner']) {
?>
<div class="pad">
	<img border="0" src="<?= $Contest['Banner'] ?>" alt="<?= $Contest['Name'] ?>" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
</div>
<?
} /* banner */
?>
<div class="linkbox">
	<a href="contest.php" class="brackets">Intro</a>
	<?=(check_perms('users_mod')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<div class="thin">
<h1>Leaderboard</h1>
<div class="box pad" style="padding: 10px 10px 10px 20px;">

<?
$Prior = Contest::get_prior_contests();
$Prior = []; // FIXME: no dropdown to see older contests (Blame Athena <3 )
if (count($Prior)) {
?>
	<form class="edit_form" style="float: right;" action="contest.php?action=leaderboard" method="post">
		<select name="leaderboard">
<?
	foreach ($Prior as $id) {
		$prior_contest = Contest::get_contest($id[0]);
		$selected = $prior_contest['ID'] == $Contest['ID'] ? ' selected' : '';
?>
			<option value="<?= $prior_contest['ID'] ?>"<?= $selected ?>><?= $prior_contest['Name'] ?></option>
<?
	}
?>
		</select>
		<input type="submit" id="view" value="view" />
	</form>
<?
} /* prior */
if (!count($Leaderboard)) {
?>
	<p>That's not supposed to happen. Looks like the contest hasn't begun yet!<p>
<?
} else {
?>
	<table class="layout">

	<tr>
	<td class="label">Rank</td>
	<td class="label">Who</td>
	<td class="label">Most recent <?= $Contest['ContestType'] == 'request_fill' ? 'fill' : 'upload'; ?></td>
	<td class="label">Most recent time</td>
	<td class="label"><?= $Contest['ContestType'] == 'request_fill' ? 'Requests Filled' : 'Perfect FLACs'; ?></td>
	</tr>
<?
	$rank = 0;
	$prev_score = 0;
	$nr_rows = 0;
	$user_seen = 0;
	foreach ($Leaderboard as $row) {
		$score = $row[1];
		if ($Contest['ContestType'] == 'request_fill' || $Contest['ContestType'] == 'upload_flac_strict_rank') {
				++$rank;
		}
		else {
			if ($score != $prev_score) {
				++$rank;
				if ($rank > $Contest['Display'] || $nr_rows > $Contest['Display']) {
					// cut off at limit, even if we haven't reached last winning place because of too many ties
					break;
				}
			}
			$prev_score = $score;
		}
		++$nr_rows;

		if ($row[0] == $LoggedUser['ID']) {
			$user_extra = " (that's you!)";
			$user_seen = 1;
		}
		else {
			$user_extra = '';
		}

		$artist_markup = '';
		$artist_id = explode(',', $row[4]);
		$artist_name = explode(chr(1), $row[5]);
		if (count($artist_id) > 2) {
			$artist_markup = 'Various Artists';
		}
		elseif (count($artist_id) == 2) {
			$artist_markup = sprintf(
				'<a href="/artist.php?id=%d">%s</a> & <a href="/artist.php?id=%d">%s</a>',
				$artist_id[0], $artist_name[0],
				$artist_id[1], $artist_name[1]
			);
		}
		else {
			$artist_markup = sprintf(
				'<a href="/artist.php?id=%d">%s</a>',
				$artist_id[0], $artist_name[0]
			);
		}

		$userinfo = Users::user_info($row[0]);
		printf(<<<END_STR
	<tr>
		<td>%d</td>
		<td><a href="/user.php?id=%d">%s</a>$user_extra</td>
		<td>%s - <a href="/torrents.php?torrentid=%d">%s</a></td>
		<td>%s</td>
		<td>%d</td>
	</tr>
END_STR
		, $rank,
			$row[0], $userinfo['Username'],
			$artist_markup,
			$row[2], $row[3], // torrent
			time_diff($row[6], 1),
			$score
		);
	}
?>
</table>
<?
	if ($Contest['is_open']) {
		// if the contest is still open, we will try to motivate them
		if (!$user_seen) {
			// the user isn't on the ladderboard, let's go through the list again and see if we can find them
			$rank = 0;
			$prev_score = 0;
			foreach ($Leaderboard as $row) {
				$score = $row[1];
				if ($Contest['ContestType'] == 'request_fill' || $Contest['ContestType'] == 'upload_flac_strict_rank') {
					++$rank;
				}
				else {
					if ($score != $prev_score) {
						++$rank;
					}
				}
				if ($row[0] == $LoggedUser['ID']) {
?>
				<p>You are currently ranked number <?=$rank?> on the leaderboard. Keep going and see if you can make it!</p>
<?
					$user_seen = 1;
					break;
				}
			}
			if (!$user_seen) {
?>
				<p>It doesn't look like you're on the leaderboard at all... upload some FLACs for fame and glory!</p>
<?
			}
		}
	}
}
?>
<!--
<p>←  <a href="/contest.php">Announcement and rules.</a></p>
-->
</div>

<? View::show_footer(); ?>
