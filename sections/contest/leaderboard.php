<?php
enforce_login();
$Contest = Contest::get_current_contest();
$Leaderboard = Contest::get_leaderboard($Contest['ID']);
View::show_header($Contest['Name']);
?>

<div class="pad">
	<img border="0" src="/static/common/contest-euterpe.png" alt="Apollo Euterpe FLAC Challenge" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
</div>

<div class="linkbox">
	<a href="contest.php" class="brackets">Intro</a>
	<?=(check_perms('users_mod')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<div class="thin">

<div class="box pad" style="padding: 10px 10px 10px 20px;">
	<h2>Leaderboard</h2>

<?php

if (!count($Leaderboard)) {
?>
	<p>That's not supposed to happen. Looks like the contest hasn't begun yet!<p>
<?php
} else {
?>
	<table class="layout">

	<tr>
	<td class="label">Rank</td>
	<td class="label">Who</td>
	<td class="label">Most recent upload</td>
	<td class="label">Most recent time</td>
	<td class="label">Perfect FLACs</td>
	</tr>
<?php
    $rank = 0;
    $prev_score = 0;
    $nr_rows = 0;
    $user_seen = 0;
    foreach ($Leaderboard as $row) {
        $score = $row[1];
        if ($score != $prev_score) {
            ++$rank;
            if ($rank > $Contest['Display'] || $nr_rows > $Contest['Display']) {
                // cut off at limit, even if we haven't reached last winning place because of too many ties
                break;
            }
        }
        $prev_score = $score;
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
        <td>%s - <a href="/torrents.php?id=%d">%s</a></td>
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
<?php
    if (!$user_seen) {
        // the user isn't on the ladderboard, let's go through the list again and see if we can find them
        $rank = 0;
        $prev_score = 0;
        foreach ($Leaderboard as $row) {
            $score = $row[1];
            if ($score != $prev_score) {
                ++$rank;
            }
            if ($row[0] == $LoggedUser['ID']) {
?>
            <p>You are currently ranked number <?=$rank?> on the leaderboard. Keep going and see if you can make it!</p>
<?php
                break;
            }
        }
?>
            <p>It doesn't look like you're on the leaderboard at all... upload some FLACs for fame and glory!</p>
<?php
    }
}
?>
<!--
<p>←  <a href="/contest.php">Announcement and rules.</a></p>
-->
</div>

<?php View::show_footer(); ?>
