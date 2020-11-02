<?php

$contest = (new Gazelle\Manager\Contest)->currentContest();
if (is_null($contest)) {
    return;
}
$leaderboard = $contest->leaderboard(3, 0);
if (empty($leaderboard)) {
    return;
}

/* Stop showing the contest results after two weeks */
if ((time() - strtotime($contest->dateEnd())) / 86400 > 15) {
    return;
}
?>

<div class="box">
    <div class="head colhead_dark"><strong>Contest Leaderboard</strong></div>
    <table>
<?php
        for ($i = 0, $end = min(3, count($leaderboard)); $i < $end; $i++) {
            $Row = $leaderboard[$i];
            $User = Users::user_info($Row['user_id']);
?>
        <tr>
            <td><a href="user.php?id=<?=$User['ID']?>"><?=$User['Username']?></a></td>
            <td><?=$Row['entry_count']?></td>
        </tr>
<?php } ?>
    </table>
    <div class="center pad">
        <a href="contest.php?action=leaderboard"><em>View More</em></a>
    </div>
</div>
