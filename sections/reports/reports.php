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
    $search->setStatus(['New', 'InProgress']);
} elseif ($_GET['view'] === 'old') {
    $search->setStatus(['Resolved']);
} else {
    error(403);
}

$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('report/index.twig', [
    'list' => (new Gazelle\Manager\Report(new Gazelle\Manager\User))->decorate(
        $search->page($paginator->limit(), $paginator->offset()),
        new Gazelle\Manager\Collage,
        new Gazelle\Manager\Comment,
        new Gazelle\Manager\Forum,
        new Gazelle\Manager\ForumThread,
        new Gazelle\Manager\ForumPost,
        new Gazelle\Manager\Request,
    ),
    'paginator' => $paginator,
    'type'      => $Types,
    'viewer'    => $Viewer,
]);
