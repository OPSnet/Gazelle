<?php

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    error(403);
}

require_once('array.php');

$search = new Gazelle\Search\Report;

if (!$Viewer->permitted('admin_reports')) {
    $search->restrictForumMod();
}

if (isset($_GET['id'])) {
    $search->setId((int)$_GET['id']);
} elseif (empty($_GET['view'])) {
    $search->setStatus('New');
} elseif ($_GET['view'] === 'old') {
    $search->setStatus('Resolved');
} else {
    error(403);
}

$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());
$idList    = $search->page($paginator->limit(), $paginator->offset());

$collageMan = new Gazelle\Manager\Collage;
$commentMan = new Gazelle\Manager\Comment;
$forumMan   = new Gazelle\Manager\Forum;
$requestMan = new Gazelle\Manager\Request;
$userMan    = new Gazelle\Manager\User;
$reportMan  = (new Gazelle\Manager\Report)->setUserManager($userMan);

$list = [];
foreach ($idList as $id) {
    $report = $reportMan->findById($id);
    switch ($report-> subjectType()) {
        case 'collage':
            $context = [
                'label'   => 'collage',
                'subject' => $collageMan->findById($report->subjectId()),
            ];
            break;
        case 'comment':
            $context = [
                'label'   => 'comment',
                'subject' => $commentMan->findById($report->subjectId()),
            ];
            break;
        case 'request':
        case 'request_update':
            $context = [
                'label'   => 'request',
                'subject' => $requestMan->findById($report->subjectId()),
            ];
            break;
        case 'thread':
            $forum = $forumMan->findByThreadId($report->subjectId());
            $link  = null;
            if ($forum) {
                $threadInfo = $forum->threadInfo($report->subjectId());
                $user = $userMan->findById($threadInfo['AuthorID']);
                $link = $forum->link() . ' &rsaquo; '
                    . $forum->threadLink($report->subjectId(), $threadInfo['Title'])
                    . ' created by ' . ($user ? $user->link() : 'System');
            }
            $context = [
                'label'   => 'forum thread',
                'subject' => $forum,
                'link'    => $link,
            ];
            break;
        case 'post':
            $forum = $forumMan->findByPostId($report->subjectId());
            $link  = null;
            if ($forum) {
                $postInfo = $forum->postInfo($report->subjectId());
                $threadInfo = $forum->threadInfo($postInfo['thread-id']);
                $user = $userMan->findById($postInfo['user-id']);
                $link = $forum->link() . ' &rsaquo; '
                    . $forum->threadLink($postInfo['thread-id'], $threadInfo['Title']) . ' &rsaquo; '
                    . $forum->threadPostLink($postInfo['thread-id'], $report->subjectId())
                    . ' by ' . ($user ? $user->link() : 'System');
            }
            $context = [
                'label'   => 'forum post',
                'subject' => $forum,
                'link'    => $link,
            ];
            break;
        case 'user':
            $context = [
                'label'   => 'user',
                'subject' => $userMan->findById($report->subjectId()),
            ];
            break;
    }
    $context['report'] = $report;
    $list[] = $context;
}

echo $Twig->render('report/index.twig', [
    'list'      => $list,
    'paginator' => $paginator,
    'type'      => $Types,
    'viewer'    => $Viewer,
]);
