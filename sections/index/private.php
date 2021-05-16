<?php
Text::$TOC = true;

$contestMan = new Gazelle\Manager\Contest;
$forumMan   = new Gazelle\Manager\Forum;
$newsMan    = new Gazelle\Manager\News;
$newsReader = new Gazelle\WitnessTable\UserReadNews;
$tgroupMan  = new Gazelle\Manager\TGroup;
$userMan    = new Gazelle\Manager\User;
$viewer     = new Gazelle\User($LoggedUser['ID']);

if ($newsMan->latestId() != -1 && $newsReader->lastRead($LoggedUser['ID']) < $newsMan->latestId()) {
    $newsReader->witness($LoggedUser['ID']);
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
    $poll = $forum->pollDataExtended($threadId, $LoggedUser['ID']);
}

View::show_header('News', 'bbcode,news_ajax');

echo $Twig->render('index/private-sidebar.twig', [
    'auth'              => $LoggedUser['AuthKey'],
    'blog'              => new Gazelle\Manager\Blog,
    'collage_count'     => (new Gazelle\Stats\Collage)->collageCount(),
    'leaderboard'       => $leaderboard,
    'featured_aotm'     => $tgroupMan->featuredAlbumAotm(),
    'featured_showcase' => $tgroupMan->featuredAlbumShowcase(),
    'staff_blog'        => new Gazelle\Manager\StaffBlog,
    'poll'              => $poll,
    'poll_thread_id'    => $threadId,
    'request_stats'     => new Gazelle\Stats\Request,
    'snatch_stats'      => $Cache->get_value('stats_snatches'),
    'torrent_stats'     => new Gazelle\Stats\Torrent,
    'user_count'        => $userMan->getEnabledUsersCount(),
    'user_stats'        => $userMan->globalActivityStats(),
    'viewer'            => $viewer,
]);

echo $Twig->render('index/private-main.twig', [
    'admin'   => $viewer->permitted('admin_manage_news'),
    'contest' => $contestMan->currentContest(),
    'latest'  => $tgroupMan->latestUploads(5),
    'news'    => $newsMan->headlines(),
]);
View::show_footer(['disclaimer'=>true]);
