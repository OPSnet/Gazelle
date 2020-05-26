<?php
$ContestMgr = new \Gazelle\Contest;

$Contest = $ContestMgr->get_current_contest();

if (empty($Contest)) {
    return;
}
$Leaderboard = $ContestMgr->get_leaderboard($Contest['ID']);
if (empty($Leaderboard)) {
    return;
}

/* Stop showing the contest results after two weeks */
if ((time() - strtotime($Contest['DateEnd'])) / 86400 > 15) {
    return;
}
?>

<div class="box">
    <div class="head colhead_dark"><strong>Contest Leaderboard</strong></div>
    <table>
<?php
        for ($i = 0; $i < min(3, count($Leaderboard)); $i++) {
            $Row = $Leaderboard[$i];
            $User = Users::user_info($Row[0]);
?>
        <tr>
            <td><a href="user.php?id=<?=$User['ID']?>"><?=$User['Username']?></a></td>
            <td><?=$Row[1]?></td>
        </tr>
<?php
        }
?>
    </table>
    <div class="center pad">
        <a href="contest.php?action=leaderboard"><em>View More</em></a>
    </div>
</div>
