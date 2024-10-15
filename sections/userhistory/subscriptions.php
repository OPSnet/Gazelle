<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$artistMan  = new Gazelle\Manager\Artist();
$collageMan = new Gazelle\Manager\Collage();
$forumMan   = new Gazelle\Manager\Forum();
$threadMan  = new Gazelle\Manager\ForumThread();
$requestMan = new Gazelle\Manager\Request();
$tgMan      = (new Gazelle\Manager\TGroup())->setViewer($Viewer);
$userMan    = new Gazelle\Manager\User();
$subscriber = new Gazelle\User\Subscription($Viewer);
$showUnread = (bool)($_GET['showunread'] ?? true);

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $showUnread
        ? $forumMan->unreadSubscribedForumTotal($Viewer) + $subscriber->unreadCommentTotal()
        : $forumMan->subscribedForumTotal($Viewer) + $subscriber->commentTotal()
);

$avatarFilter = Gazelle\Util\Twig::factory()->createTemplate('{{ user|avatar(viewer)|raw }}');

$Results = (new Gazelle\User\Subscription($Viewer))->latestSubscriptionList($showUnread, $paginator->limit(), $paginator->offset());
foreach ($Results as &$result) {
    $postLink = $result['PostID'] ? "&amp;postid={$result['PostID']}#post{$result['PostID']}" : '';
    switch ($result['Page']) {
        case 'artist':
            $artist = $artistMan->findById($result['PageID']);
            if ($artist) {
                $result = $result + [
                    'jump' => $artist->url() . $postLink,
                    'link' => 'Artist › ' . $artist->link(),
                ];
            }
            break;
        case 'collages':
            $collage = $collageMan->findById($result['PageID']);
            if ($collage) {
                $result = $result + [
                    'jump' => $collage->url() . $postLink,
                    'link' => "Collage › {$collage->link()}",
                ];
            }
            break;
        case 'requests':
            $request = $requestMan->findById($result['PageID']);
            if ($request) {
                $result = $result + [
                    'jump' => $request->url() . $postLink,
                    'link' => "Request › {$request->smartLink()}",
                ];
            }
            break;
        case 'torrents':
            $tgroup = $tgMan->findById($result['PageID']);
            if ($tgroup) {
                $result = $result + [
                    'jump' => $tgroup->url() . $postLink,
                    'link' => "Torrent › {$tgroup->link()}",
                ];
            }
            break;
        case 'forums':
            $thread = $threadMan->findById($result['PageID']);
            if ($thread) {
                $result = $result + [
                    'jump' => $thread->url() . $postLink,
                    'link' => "Forums › {$thread->forum()->link()} › {$thread->link()}",
                ];
            }
            break;
        default:
            error('Unknown comment history target');
    }
    if (!empty($result['LastReadBody'])) {
        $result['avatar'] = $avatarFilter->render(['user' => new Gazelle\User($result['LastReadUserID']), 'viewer' => $Viewer]);
    }
    if ($result['LastReadEditedUserID']) {
        $result['editor_link'] = $userMan->findById($result['LastReadEditedUserID'])->link();
    }
}
unset($result);

echo $Twig->render('user/subscription-history.twig', [
    'page'           => $Results,
    'paginator'      => $paginator,
    'show_collapsed' => (bool)($_GET['collapse'] ?? true),
    'show_unread'    => $showUnread,
    'viewer'         => $Viewer,
]);
