<?php
enforce_login();
if (isset($_POST['leaderboard']) && is_number($_POST['leaderboard'])) {
    $Contest = $ContestMgr->get_contest(intval($_POST['leaderboard']));
}
elseif (isset($_GET['id']) && is_number($_GET['id'])) {
    $Contest = $ContestMgr->get_contest(intval($_GET['id']));
}
else {
    $Contest = $ContestMgr->get_current_contest();
}

if (!empty($Contest)) {
    $Leaderboard = $ContestMgr->get_leaderboard($Contest['ID']);
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
<?php
} /* banner */
?>
<div class="linkbox">
    <a href="contest.php" class="brackets">Intro</a>
    <?=(check_perms('admin_manage_contest')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<div class="thin">
<h1>Leaderboard</h1>
<div class="box pad" style="padding: 10px 10px 10px 20px;">

<?php
$Prior = $ContestMgr->get_prior_contests();
if (check_perms('admin_manage_contest') && count($Prior)) {
?>
    <form class="edit_form" style="float: right;" action="contest.php?action=leaderboard" method="post">
        <select name="leaderboard">
<?php
    foreach ($Prior as $id) {
        $prior_contest = $ContestMgr->get_contest($id[0]);
        $selected = $prior_contest['ID'] == $Contest['ID'] ? ' selected' : '';
?>
            <option value="<?= $prior_contest['ID'] ?>"<?= $selected ?>><?= $prior_contest['Name'] ?></option>
<?php
    }
?>
        </select>
        <input type="submit" id="view" value="view" />
    </form>
<?php
} /* prior */
?>

    <div class="head">
<?php
if ($Contest['BonusPool'] > 0) {
    $bp = new \Gazelle\BonusPool($Contest['BonusPool']);
?>
        <h3>The Bonus Point pool currently stands at <?= number_format($bp->getTotalSent()) ?> points.</h3>
<?php
} ?>

<?php
if (!count($Leaderboard)) {
    if ($Contest['is_open']) {
?>
    <p>The scheduler has not run yet, there are no results to display.<p>
<?php
    }
    else {
?>
    <p>That's not supposed to happen. Looks like the contest hasn't begun yet!<p>
<?php
    }
?>
<?php
} else {
?>
        <h3>A grand total of <?=
            G::$Cache->get_value("contest_leaderboard_total_{$Contest['ID']}")
            ?: "<span title=\"We will recalculate the numbers soon\">many, many, many</span>"
        ?> <?= $Contest['ContestType'] == 'request_fill' ? 'requests have been filled' : 'torrents have been uploaded' ?>.</h3>
    </div>
    <table class="layout">

    <tr>
    <td class="label" style="text-align:left">Rank</td>
    <td class="label" style="text-align:left">Who</td>
    <td class="label" style="text-align:left">Most recent <?= $Contest['ContestType'] == 'request_fill' ? 'fill' : 'upload'; ?></td>
    <td class="label" style="text-align:left">Most recent time</td>
    <td class="label" style="text-align:left"><?= $Contest['ContestType'] == 'request_fill' ? 'Requests Filled' : 'Perfect FLACs'; ?></td>
    </tr>
<?php
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
            if ($prev_score != $score) {
                ++$rank;
            }
            $prev_score = $score;
        }
        if ($rank > $Contest['Display'] || $nr_rows > $Contest['Display']) {
            // cut off at limit, even if we haven't reached last winning place because of too many ties
            break;
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
            $artist_markup = 'Various Artists - ';
        }
        elseif (count($artist_id) == 2) {
            $artist_markup = sprintf(
                '<a href="/artist.php?id=%d">%s</a> & <a href="/artist.php?id=%d">%s</a> - ',
                $artist_id[0], $artist_name[0],
                $artist_id[1], $artist_name[1]
            );
        }
        //For non-music torrents, $artist_id[0] does exist but has no value.
        else {
            if ((string)$artist_id[0] == '') {
                $artist_markup = '';
            }
            else {
            $artist_markup = sprintf(
                '<a href="/artist.php?id=%d">%s</a> - ',
                $artist_id[0], $artist_name[0]
            );
            }
        }

        $userinfo = Users::user_info($row[0]);
        printf(<<<END_STR
    <tr>
        <td>%d</td>
        <td><a href="/user.php?id=%d">%s</a>$user_extra</td>
        <td>%s<a href="/torrents.php?torrentid=%d">%s</a></td>
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
                <p>With your <?=$score?> upload<?= $score == 1 ? '' : 's' ?>, you are currently ranked number <?=$rank?> on the leaderboard. Keep going and see if you can make it!</p>
<?php
                    $user_seen = 1;
                    break;
                }
            }
            if (!$user_seen) {
?>
                <p>It doesn't look like you're on the leaderboard at all... <?= $Contest['ContestType'] == 'request_fill' ? 'fill some requests' : 'upload some FLACs' ?> for fame and glory!</p>

<?php
            }
        }
    }
}
?>
</div>

<?php
View::show_footer();
