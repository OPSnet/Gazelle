<?php

$id = (int)$_GET['id'];
if (!$id) {
    error(404);
}

require_once('array.php');
if (!isset($Types[$_GET['type'] ?? ''])) {
    error(403);
}
$type = $_GET['type'];
$reportType = $Types[$type];

switch ($type) {
    case 'user':
        $user = (new Gazelle\Manager\User)->findById($id);
        if (is_null($user)) {
            error(404);
        }
        $report = new Gazelle\Report\User($user);
        break;

    case 'request':
        $request = (new Gazelle\Manager\Request)->findById($id);
        if (is_null($request)) {
            error(404);
        }
        $report = new Gazelle\Report\Request($request);
        break;

    case 'request_update':
        $request = (new Gazelle\Manager\Request)->findById($id);
        if (is_null($request)) {
            error(404);
        }
        if ($request->isFilled() || $request->categoryName() != 'Music' || $request->year() != 0) {
            error(403);
        }
        $report = (new Gazelle\Report\Request($request))->isUpdate(true);
        break;

    case 'collage':
        $collage = (new Gazelle\Manager\Collage)->findById($id);
        if (is_null($collage)) {
            error(404);
        }
        $report = new Gazelle\Report\Collage($collage);
        break;

    case 'thread':
        $forum = (new Gazelle\Manager\Forum)->findByThreadId($id);
        if (is_null($forum)) {
            error(404);
        }
        if (!$Viewer->readAccess($forum)) {
            error(403);
        }
        $report = (new Gazelle\Report\ForumThread($forum))->setContext($id);
        break;

    case 'post':
        $forum = (new Gazelle\Manager\Forum)->findByPostId($id);
        if (is_null($forum)) {
            error(404);
        }
        if (!$Viewer->readAccess($forum)) {
            error(403);
        }
        $report = (new Gazelle\Report\ForumPost($forum))->setContext($id);
        break;

    case 'comment':
        $comment = (new Gazelle\Manager\Comment)->findById($id);
        if (is_null($comment)) {
            error(404);
        }
        $report = (new Gazelle\Report\Comment($comment))->setContext($reportTypes[$type]['title']);
        break;
}

echo $Twig->render('report/create.twig', [
    'id'          => $id,
    'release'     => (new Gazelle\ReleaseType)->list(),
    'report'      => $report,
    'report_type' => $reportType,
    'type'        => $type,
    'viewer'      => $Viewer,
]);
