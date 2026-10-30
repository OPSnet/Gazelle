<?php

declare(strict_types=1);

namespace Gazelle\Route\Index;

use Gazelle\Contest;
use Gazelle\Enum\FeaturedAlbumType;
use Gazelle\Error400;
use Gazelle\Manager;
use Gazelle\Route;
use Gazelle\SiteInfo;
use Gazelle\Stats;
use Gazelle\WitnessTable;

class Authenticated extends Route\Util\ActionRoute {
    protected const array ACTIONS = [
        'poll' => self::class,
        ''     => self::class,
    ];

    public function process(): void {
        if ($this->action === 'poll') {
            $Viewer = $this->ctx->viewer();
            require __DIR__ . '/../../../sections/forums/poll_vote.php';
        } else {
            $this->indexPage();
        }
    }

    public function indexPage(): void {
        \Text::$TOC = true;

        $featured   = new Manager\FeaturedAlbum();
        $contestMan = new Manager\Contest();
        $newsMan    = new Manager\News();
        $newsReader = new WitnessTable\UserReadNews();
        $torMan     = new Manager\Torrent();

        if (
            $newsMan->latestId() != -1
            && $newsReader->lastRead($this->ctx->viewer()) < $newsMan->latestId()
        ) {
            $newsReader->witness($this->ctx->viewer());
        }

        $contest = $contestMan->currentContest();
        [$contestRank, $leaderboard] = $this->contestRank($contest);

        echo $this->twig->render('index/private-sidebar.twig', [
            'blog'          => new Manager\Blog(),
            'collage_count' => new Stats\Collage()->collageTotal(),
            'contest_rank'  => $contestRank,
            'leaderboard'   => $leaderboard,
            'aotm'          => $featured->findByType(FeaturedAlbumType::AlbumOfTheMonth),
            'showcase'      => $featured->findByType(FeaturedAlbumType::Showcase),
            'staff_blog'    => new Manager\StaffBlog(),
            'poll'          => new Manager\ForumPoll()->findByFeaturedPoll(),
            'request_stats' => new Stats\Request(),
            'site_info'     => new SiteInfo(),
            'torrent_stats' => new Stats\Torrent(),
            'user_stats'    => new Stats\Users(),
            'viewer'        => $this->ctx->viewer(),
        ]);

        echo $this->twig->render('index/private-main.twig', [
            'admin'   => (int)$this->ctx->viewer()->permitted('admin_manage_news'),
            'contest' => $contest,
            'latest'  => $torMan->latestUploads(5),
            'news'    => $newsMan->headlines(),
        ]);
    }

    public function contestRank(?Contest $contest): ?array {
        if (!$contest) {
            return null;
        }
        $leaderboard = $contest->leaderboard(CONTEST_ENTRIES_PER_PAGE, 0);
        if (!$leaderboard || (time() - (int)strtotime($contest->dateEnd())) / 86400 > 15) {
            /* Stop showing the contest results after two weeks */
            return null;
        }
        $leaderboard = array_slice($leaderboard, 0, 3);
        $userMan = new Manager\User();
        foreach ($leaderboard as &$entry) {
            $entry['username'] = $userMan->findById($entry['user_id'])->username();
        }
        $contestRank = $contest->rank($this->ctx->viewer());
        return [$contestRank, $leaderboard];
    }
}
