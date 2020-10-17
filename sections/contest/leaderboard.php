<?php
enforce_login();
if (!empty($_POST['leaderboard'])) {
    $contest = new Gazelle\Contest((int)$_POST['leaderboard']);
}
elseif (!empty($_GET['id'])) {
    $contest = new Gazelle\Contest((int)$_GET['id']);
}
else {
    $contest = $contestMan->currentContest();
}

if (!empty($contest)) {
    $leaderboard = $contest->leaderboard();
    View::show_header($contest->name() . ' Leaderboard');
}
else {
    View::show_header('Leaderboard');
}

if ($contest->banner()) {
?>
<div class="pad">
    <img border="0" src="<?= $contest->banner() ?>" alt="<?= $contest->name() ?>" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
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
$prior = $contestMan->priorContests();
if (check_perms('admin_manage_contest') && count($prior)) {
?>
    <form class="edit_form" style="float: right;" action="contest.php?action=leaderboard" method="post">
        <select name="leaderboard">
<?php foreach ($prior as $p) { ?>
            <option value="<?= $p->id() ?>"<?= $p->id() === $contest->id() ? ' selected' : '' ?>><?= $p->name() ?></option>
<?php } ?>
        </select>
        <input type="submit" id="view" value="view" />
    </form>
<?php } ?>
    <div class="head">
<?php
if ($contest->hasBonusPool()) {
?>
        <h3>The Bonus Point pool currently stands at <?= number_format($contest->bonusPoolTotal()) ?> points.</h3>
<?php
} ?>

<?php
if (!count($leaderboard)) {
    if ($contest->isOpen()) {
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
    $isRequestFill = $contest instanceof \Gazelle\Contest\RequestFill;
?>
        <h3>A grand total of <?= $contest->totalEntries()
            ?: '<span title="We will recalculate the numbers soon">many, many, many</span>'
        ?> <?= $isRequestFill ? 'requests have been filled' : 'torrents have been uploaded' ?>.</h3>
    </div>
    <table class="layout">

    <tr>
    <th style="text-align:left">Rank</th>
    <th style="text-align:left">Who</th>
    <th style="text-align:left">Most recent <?= $isRequestFill ? 'fill' : 'upload'; ?></th>
    <th style="text-align:left">Most recent time</th>
    <th style="text-align:left"><?= $isRequestFill ? 'Requests Filled' : 'Perfect FLACs'; ?></th>
    </tr>
<?php
    $torMan = new Gazelle\Manager\Torrent;
    $labelMan = new Gazelle\Manager\TorrentLabel;
    $labelMan->showMedia(true)->showEdition(true)->showFlags(false);

    $rank = 0;
    $prevScore = 0;
    $nrRows = 0;
    $userSeen = 0;
    foreach ($leaderboard as $row) {

        $score = $row['entry_count'];
        if ($isRequestFill) {
                ++$rank;
        }
        else {
            if ($prevScore != $score) {
                ++$rank;
            }
            $prevScore = $score;
        }
        if ($rank > $contest->display() || $nrRows > $contest->display()) {
            // cut off at limit, even if we haven't reached last winning place because of too many ties
            break;
        }
        ++$nrRows;

        if ($row['user_id'] == $LoggedUser['ID']) {
            $userExtra = "&nbsp;(that's&nbsp;you!)";
            $userSeen = 1;
        }
        else {
            $userExtra = '';
        }
        [$group, $torrent] = $torMan->setTorrentId($row['last_entry_id'])->torrentInfo();

        printf(<<<END_STR
    <tr>
        <td>%d</td>
        <td><a href="/user.php?id=%d">%s</a>$userExtra</td>
        <td>%s - <a href="torrents.php?id=%d&amp;torrentid=%d">%s</a> - %s</td>
        <td>%s</td>
        <td>%d</td>
    </tr>
END_STR
        , $rank,
            $row['user_id'], Users::user_info($row['user_id'])['Username'],
            $torMan->artistHtml(), $group['ID'], $row['last_entry_id'], $group['Name'], $labelMan->load($torrent)->label(),
            time_diff($row['last_upload'], 1),
            $score
        );
    }
?>
</table>
<?php
    if ($contest->isOpen()) {
        // if the contest is still open, we will try to motivate them
        if (!$userSeen) {
            // the user isn't on the ladderboard, let's go through the list again and see if we can find them
            $rank = 0;
            $prevScore = 0;
            foreach ($leaderboard as $row) {
                $score = $row[1];
                if ($isRequestFill) {
                    ++$rank;
                }
                elseif ($score != $prevScore) {
                    ++$rank;
                }
                if ($row[0] == $LoggedUser['ID']) {
?>
                <p>With your <?= $score ?> upload<?= plural($score) ?>, you are currently ranked number <?= $rank ?> on the leaderboard. Keep going and see if you can make it!</p>
<?php
                    $userSeen = 1;
                    break;
                }
            }
            if (!$userSeen) {
?>
                <p>It doesn't look like you're on the leaderboard at all... <?= $isRequestFill ? 'fill some requests' : 'upload some FLACs' ?> for fame and glory!</p>

<?php
            }
        }
    }
}
?>
</div>

<?php
View::show_footer();
