<?php
Text::$TOC = true;

$user = new Gazelle\User($LoggedUser['ID']);
$newsMan = new Gazelle\Manager\News;
$latestNewsId = $newsMan->latestId();
if ($LoggedUser['LastReadNews'] < $latestNewsId) {
    $user->updateLastReadNews($latestNewsId);
    $LoggedUser['LastReadNews'] = $latestNewsId;
}

View::show_header('News', 'bbcode,news_ajax');
?>
<div class="thin">
    <div class="sidebar">
<?php
require('month_album.php');
require('vanity_album.php');

if (check_perms('users_mod')) {
?>
        <div class="box">
            <div class="head colhead_dark">
                <strong><a href="staffblog.php">Latest staff blog posts</a></strong>
            </div>
            <ul class="stats nobullet">
<?php
    $blogMan = new Gazelle\Manager\StaffBlog;
    $Blog = array_slice($blogMan->blogList(), 0, 5);
    $read = $blogMan->readBy($LoggedUser['ID']);
    foreach ($Blog as $article) {
        $unread = $read < strtotime($article['created']);
?>
                <li>
                    <?= $unread ? '<strong>' : ''?>
                    <a href="staffblog.php#blog<?= $article['id'] ?>"><?= $article['title'] ?></a>
                    <?= $unread ? '</strong>' : ''?>
                </li>
<?php } ?>
            </ul>
        </div>
<?php } /* users_mod */ ?>

        <div class="box">
            <div class="head colhead_dark"><strong><a href="blog.php">Latest blog posts</a></strong></div>
            <ul class="stats">
<?php
$blogMan = new Gazelle\Manager\Blog;
$headlines = array_slice($blogMan->headlines(), 0, 5);
foreach ($headlines as $article) {
    [$BlogID, $Title] = $article;
?>
                <li>
                    <a href="blog.php#blog<?=$BlogID?>"><?=$Title?></a>
                </li>
<?php } ?>
            </ul>
        </div>
<?php require('contest_leaderboard.php'); ?>
        <div class="box">
            <div class="head colhead_dark"><strong>Stats</strong></div>
            <ul class="stats nobullet">
<?php if (USER_LIMIT > 0) { ?>
                <li>Maximum users: <?=number_format(USER_LIMIT) ?></li>
<?php
}

$userMan = new Gazelle\Manager\User;
$UserCount = $userMan->getEnabledUsersCount();
$UserStats = $userMan->globalActivityStats();
$torrentStatsMan = new Gazelle\Stats\Torrent;
?>
                <li>Enabled users: <?=number_format($UserCount)?> <a href="stats.php?action=users" class="brackets">Details</a></li>
                <li>Users active today: <?=number_format($UserStats['Day'])?> (<?=number_format($UserStats['Day'] / $UserCount * 100, 2)?>%)</li>
                <li>Users active this week: <?=number_format($UserStats['Week'])?> (<?=number_format($UserStats['Week'] / $UserCount * 100, 2)?>%)</li>
                <li>Users active this month: <?=number_format($UserStats['Month'])?> (<?=number_format($UserStats['Month'] / $UserCount * 100, 2)?>%)</li>
                <li>Torrents: <?=number_format($torrentStatsMan->torrentCount()) ?></li>
                <li>Releases: <?=number_format($torrentStatsMan->albumCount()) ?></li>
                <li>Artists: <?=number_format($torrentStatsMan->artistCount())?></li>
                <li>"Perfect" FLACs: <?=number_format($torrentStatsMan->perfectCount())?></li>
                <li>Collages: <?= number_format((new Gazelle\Stats\Collage)->collageCount()) ?></li>
<?php
if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $DB->prepared_query('
        SELECT count(*), sum(FillerID > 0)
        FROM requests
    ');
    list($RequestCount, $FilledCount) = $DB->next_record();
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 3600 * 3 + rand(0, 1800)); // three hours plus fuzz
} else {
    list($RequestCount, $FilledCount) = $RequestStats;
}
$RequestPercentage = $RequestCount > 0 ? $FilledCount / $RequestCount * 100 : 0;
?>
                <li>Requests: <?=number_format($RequestCount)?> (<?=number_format($RequestPercentage, 2)?>% filled)</li>
<?php

if ($SnatchStats = $Cache->get_value('stats_snatches')) {
?>
                <li>Snatches: <?=number_format($SnatchStats)?></li>
<?php
}

if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    //Cache lock!
    $PeerStatsLocked = $Cache->get_value('stats_peers_lock');
    if (!$PeerStatsLocked) {
        $Cache->cache_value('stats_peers_lock', 1, 30);
        $DB->prepared_query("
            SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type
        ");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $SeederCount = $PeerCount['Seeding'][1] ?: 0;
        $LeecherCount = $PeerCount['Leeching'][1] ?: 0;
        $Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 1209600); // 2 week cache
        $Cache->delete_value('stats_peers_lock');
    }
} else {
    $PeerStatsLocked = false;
    list($LeecherCount, $SeederCount) = $PeerStats;
}

if (!$PeerStatsLocked) {
    $Ratio = Format::get_ratio_html($SeederCount, $LeecherCount);
    $PeerCount = number_format($SeederCount + $LeecherCount);
    $SeederCount = number_format($SeederCount);
    $LeecherCount = number_format($LeecherCount);
} else {
    $PeerCount = $SeederCount = $LeecherCount = $Ratio = 'Server busy';
}
?>
                <li>Peers: <?=$PeerCount?></li>
                <li>Seeders: <?=$SeederCount?></li>
                <li>Leechers: <?=$LeecherCount?></li>
                <li>Seeder/leecher ratio: <?=$Ratio?></li>
            </ul>
        </div>
<?php
if (($TopicID = $Cache->get_value('polls_featured')) === false) {
    $DB->prepared_query('
        SELECT TopicID
        FROM forums_polls
        WHERE Featured IS NOT NULL
        ORDER BY Featured DESC
        LIMIT 1
    ');
    list($TopicID) = $DB->next_record();
    $Cache->cache_value('polls_featured', $TopicID, 3600 * 6);
}
if ($TopicID) {
    $forum = new \Gazelle\Forum;
    list($Question, $Answers, $Votes, $Featured, $Closed) = $forum->pollData($TopicID);

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $UserResponse = $DB->scalar("
        SELECT Vote
        FROM forums_polls_votes
        WHERE UserID = ?
            AND TopicID = ?
        ", $LoggedUser['ID'], $TopicID
    );
    if ($UserResponse > 0) {
        $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
    }
?>
        <div class="box">
            <div class="head colhead_dark"><strong>Poll<?php if ($Closed) { echo ' [Closed]'; } ?></strong></div>
            <div class="pad">
                <p><strong><?=display_str($Question)?></strong></p>
<?php    if ($UserResponse !== null || $Closed) { ?>
                <ul class="poll nobullet">
<?php        foreach ($Answers as $i => $Answer) {
            if ($TotalVotes > 0) {
                $Ratio = $Votes[$i] / $MaxVotes;
                $Percent = $Votes[$i] / $TotalVotes;
            } else {
                $Ratio = 0;
                $Percent = 0;
            }
?>                    <li><?=display_str($Answers[$i])?> (<?=number_format($Percent * 100, 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($Ratio * 100, 2)?>%;"></span>
                        <span class="right_poll"></span>
                        <br />
                    </li>
<?php        } ?>
                </ul>
                <strong>Votes:</strong> <?=number_format($TotalVotes)?><br />
<?php     } else { ?>
                <div id="poll_container">
                <form class="vote_form" name="poll" id="poll" action="">
                    <input type="hidden" name="action" value="poll" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="topicid" value="<?=$TopicID?>" />
<?php         foreach ($Answers as $i => $Answer) { ?>
                    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
                    <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php         } ?>
                    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br /><br />
                    <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response } );" value="Vote" />
                </form>
                </div>
<?php     } ?>
                <br /><strong>Topic:</strong> <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>">Visit</a>
            </div>
        </div>
<?php } ?>
    </div>
<?= G::$Twig->render('index/private-main.twig', [
    'admin'   => check_perms('admin_manage_news'),
    'contest' => (new Gazelle\Manager\Contest)->currentContest(),
    'latest'  => (new \Gazelle\Manager\Torrent)->latestUploads(5),
    'news'    => array_slice($newsMan->headlines(), 0, 5),
]);
?>

</div>
<?php
View::show_footer(['disclaimer'=>true]);
