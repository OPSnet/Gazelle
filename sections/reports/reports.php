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
$threadMan  = new Gazelle\Manager\ForumThread;
$postMan    = new Gazelle\Manager\ForumPost;
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
            $thread = $threadMan->findById($report->subjectId());
            $context = [
                'label'   => 'forum thread',
                'subject' => $thread,
                'link'    => $thread
                    ?  ($thread->forum()->link() . ' &rsaquo; ' . $thread->link()
                        . ' created by ' . ($thread?->author()->link() ?? 'System'))
                    : null,
            ];
            break;
        case 'post':
            $post = $postMan->findById($report->subjectId());
            $link  = null;
            if ($post) {
                $thread = $post->thread();
                $link = $thread->forum()->link() . ' &rsaquo; ' . $thread->link() . ' &rsaquo; ' . $post->link()
                    . ' posted by ' . ($userMan->findById($post->userId())?->link() ?? 'System');
            }
            $context = [
                'label'   => 'forum post',
                'subject' => $post,
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
