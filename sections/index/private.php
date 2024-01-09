<?php

use Gazelle\Enum\FeaturedAlbumType;

Text::$TOC = true;

$featured   = new Gazelle\Manager\FeaturedAlbum;
$contestMan = new Gazelle\Manager\Contest;
$newsMan    = new Gazelle\Manager\News;
$newsReader = new Gazelle\WitnessTable\UserReadNews;
$tgMan      = new Gazelle\Manager\TGroup;
$torMan     = new Gazelle\Manager\Torrent;

if ($newsMan->latestId() != -1 && $newsReader->lastRead($Viewer) < $newsMan->latestId()) {
    $newsReader->witness($Viewer);
}

$contest     = $contestMan->currentContest();
$contestRank = null;
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
            $userMan = new Gazelle\Manager\User;
            foreach ($leaderboard as &$entry) {
                $entry['username'] = $userMan->findById($entry['user_id'])->username();
            }
            unset($entry);
            $contestRank = $contest->rank($Viewer);
        }
    }
}

echo $Twig->render('index/private-sidebar.twig', [
    'blog'          => new Gazelle\Manager\Blog,
    'collage_count' => (new Gazelle\Stats\Collage)->collageTotal(),
    'contest_rank'  => $contestRank,
    'leaderboard'   => $leaderboard,
    'aotm'          => $featured->findByType(FeaturedAlbumType::AlbumOfTheMonth),
    'showcase'      => $featured->findByType(FeaturedAlbumType::Showcase),
    'staff_blog'    => new Gazelle\Manager\StaffBlog,
    'poll'          => (new Gazelle\Manager\ForumPoll)->findByFeaturedPoll(),
    'request_stats' => new Gazelle\Stats\Request,
    'torrent_stats' => new Gazelle\Stats\Torrent,
    'user_stats'    => new Gazelle\Stats\Users,
    'viewer'        => $Viewer,
]);

echo $Twig->render('index/private-main.twig', [
    'admin'   => (int)$Viewer->permitted('admin_manage_news'),
    'contest' => $contestMan->currentContest(),
    'latest'  => $torMan->latestUploads(5),
    'news'    => $newsMan->headlines(),
]);
