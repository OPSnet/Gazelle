<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\FeaturedAlbumType;

\Text::$TOC = true;

$featured   = new Manager\FeaturedAlbum();
$contestMan = new Manager\Contest();
$newsMan    = new Manager\News();
$newsReader = new WitnessTable\UserReadNews();
$tgMan      = new Manager\TGroup();
$torMan     = new Manager\Torrent();

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
        if ((time() - (int)strtotime($contest->dateEnd())) / 86400 > 15) {
            $leaderboard = [];
        } else {
            $leaderboard = array_slice($leaderboard, 0, 3);
            $userMan = new Manager\User();
            foreach ($leaderboard as &$entry) {
                $entry['username'] = $userMan->findById($entry['user_id'])->username();
            }
            unset($entry);
            $contestRank = $contest->rank($Viewer);
        }
    }
}

echo $Twig->render('index/private-sidebar.twig', [
    'blog'          => new Manager\Blog(),
    'collage_count' => new Stats\Collage()->collageTotal(),
    'contest_rank'  => $contestRank,
    'leaderboard'   => $leaderboard,
    'aotm'          => $featured->findByType(FeaturedAlbumType::AlbumOfTheMonth),
    'showcase'      => $featured->findByType(FeaturedAlbumType::Showcase),
    'staff_blog'    => new Manager\StaffBlog(),
    'poll'          => new Manager\ForumPoll()->findByFeaturedPoll(),
    'request_stats' => new Stats\Request(),
    'torrent_stats' => new Stats\Torrent(),
    'user_stats'    => new Stats\Users(),
    'viewer'        => $Viewer,
]);

echo $Twig->render('index/private-main.twig', [
    'admin'   => (int)$Viewer->permitted('admin_manage_news'),
    'contest' => $contestMan->currentContest(),
    'latest'  => $torMan->latestUploads(5),
    'news'    => $newsMan->headlines(),
]);
