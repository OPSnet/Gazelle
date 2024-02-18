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
        $user = (new Gazelle\Manager\User())->findById($id);
        if (is_null($user)) {
            error(404);
        }
        $report = new Gazelle\Report\User($id, $user);
        break;

    case 'request':
        $request = (new Gazelle\Manager\Request())->findById($id);
        if (is_null($request)) {
            error(404);
        }
        $report = new Gazelle\Report\Request($id, $request);
        break;

    case 'request_update':
        $request = (new Gazelle\Manager\Request())->findById($id);
        if (is_null($request)) {
            error(404);
        }
        if ($request->isFilled() || $request->categoryName() != 'Music' || $request->year() != 0) {
            error(403);
        }
        $report = (new Gazelle\Report\Request($id, $request))->isUpdate(true);
        break;

    case 'collage':
        $collage = (new Gazelle\Manager\Collage())->findById($id);
        if (is_null($collage)) {
            error(404);
        }
        $report = new Gazelle\Report\Collage($id, $collage);
        break;

    case 'thread':
        $thread = (new Gazelle\Manager\ForumThread())->findById($id);
        if (is_null($thread)) {
            error(404);
        }
        if (!$Viewer->readAccess($thread->forum())) {
            error(403);
        }
        $report = new Gazelle\Report\ForumThread($id, $thread);
        break;

    case 'post':
        $post = (new Gazelle\Manager\ForumPost())->findById($id);
        if (is_null($post)) {
            error(404);
        }
        if (!$Viewer->readAccess($post->thread()->forum())) {
            error(403);
        }
        $report = new Gazelle\Report\ForumPost($id, $post);
        break;

    case 'comment':
        $comment = (new Gazelle\Manager\Comment())->findById($id);
        if (is_null($comment)) {
            error(404);
        }
        $report = (new Gazelle\Report\Comment($id, $comment))->setContext($reportType['title']);
        break;
    default:
        error(0);
}

echo $Twig->render('report/create.twig', [
    'id'          => $id,
    'release'     => (new Gazelle\ReleaseType())->list(),
    'report'      => $report,
    'report_type' => $reportType,
    'type'        => $type,
    'viewer'      => $Viewer,
]);
