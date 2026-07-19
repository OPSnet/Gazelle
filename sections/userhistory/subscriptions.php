<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$artistMan  = new Manager\Artist();
$collageMan = new Manager\Collage();
$forumMan   = new Manager\Forum();
$threadMan  = new Manager\ForumThread();
$requestMan = new Manager\Request();
$tgMan      = new Manager\TGroup();
$userMan    = new Manager\User();
$subscriber = new User\Subscription($Viewer);
$showUnread = (bool)($_GET['showunread'] ?? true);

$paginator = new Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $showUnread
        ? $forumMan->unreadSubscribedForumTotal($Viewer) + $subscriber->unreadCommentTotal()
        : $forumMan->subscribedForumTotal($Viewer) + $subscriber->commentTotal()
);

$avatarFilter = Util\Twig::factory($userMan)->createTemplate('{{ user|avatar(viewer)|raw }}');

$Results = new User\Subscription($Viewer)->latestSubscriptionList($showUnread, $paginator->limit(), $paginator->offset());
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
            Error400::error('Unknown comment history target');
    }
    if (!empty($result['LastReadBody'])) {
        $result['avatar'] = $avatarFilter->render(['user' => new User($result['LastReadUserID']), 'viewer' => $Viewer]);
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
