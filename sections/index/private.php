<?php
Text::$TOC = true;

$contestMan = new Gazelle\Manager\Contest;
$forumMan   = new Gazelle\Manager\Forum;
$newsMan    = new Gazelle\Manager\News;
$newsReader = new Gazelle\WitnessTable\UserReadNews;
$tgMan      = new Gazelle\Manager\TGroup;
$torMan     = new Gazelle\Manager\Torrent;
$userMan    = new Gazelle\Manager\User;

if ($newsMan->latestId() != -1 && $newsReader->lastRead($Viewer->id()) < $newsMan->latestId()) {
    $newsReader->witness($Viewer->id());
}

$contest = $contestMan->currentContest();
if (!$contest) {
    $leaderboard = [];
} else {
    $leaderboard = $contest->leaderboard(CONTEST_ENTRIES_PER_PAGE, 0);
    if ($leaderboard) {
        /* Stop showing the contest results after two weeks */
        if ((time() - strtotime($contest->dateEnd())) / 86400 > 15) {
            $leaderboard = [];
        } else {
            $leaderboard = array_slice($leaderboard, 0, 3);
            foreach ($leaderboard as &$entry) {
                $entry['username'] = $userMan->findById($entry['user_id'])->username();
            }
            unset($entry);
        }
    }
}

$threadId = $forumMan->findThreadIdByFeaturedPoll();
if (!$threadId) {
    $poll = false;
} else {
    $forum = $forumMan->findByThreadId($threadId);
    $poll = $forum->pollDataExtended($threadId, $Viewer->id());
}

echo $Twig->render('index/private-sidebar.twig', [
    'auth'              => $Viewer->auth(),
    'blog'              => new Gazelle\Manager\Blog,
    'collage_count'     => (new Gazelle\Stats\Collage)->collageCount(),
    'leaderboard'       => $leaderboard,
    'featured_aotm'     => $tgMan->featuredAlbumAotm(),
    'featured_showcase' => $tgMan->featuredAlbumShowcase(),
    'staff_blog'        => new Gazelle\Manager\StaffBlog,
    'poll'              => $poll,
    'poll_thread_id'    => $threadId,
    'request_stats'     => new Gazelle\Stats\Request,
    'snatch_stats'      => $Cache->get_value('stats_snatches'),
    'torrent_stats'     => new Gazelle\Stats\Torrent,
    'user_count'        => $userMan->getEnabledUsersCount(),
    'user_stats'        => $userMan->globalActivityStats(),
    'viewer'            => $Viewer,
]);

echo $Twig->render('index/private-main.twig', [
    'admin'   => $Viewer->permitted('admin_manage_news'),
    'contest' => $contestMan->currentContest(),
    'latest'  => $torMan->latestUploads(5),
    'news'    => $newsMan->headlines(),
]);
