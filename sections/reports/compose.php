<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$ReportID = (int)($_GET['reportid'] ?? 0);
$id       = (int)($_GET['thingid'] ?? 0);
$type     = $_GET['type'] ?? null;
if (!$ReportID || !$id || is_null($type)) {
    error(403);
}

require_once('array.php');
$reportType = $Types[$type];

$user = null;
if (!isset($Return)) {
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['toid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
    if ($user->id() === $Viewer->id()) {
        error("You cannot start a conversation with yourself!");
        header('Location: inbox.php');
    }
}

switch ($type) {
    case 'user':
        $reported = (new Gazelle\Manager\User)->findById($id);
        if (is_null($reported)) {
            error(404);
        }
        $report = new Gazelle\Report\User($reported);
        break;

    case 'request':
    case 'request_update':
        $request = (new Gazelle\Manager\Request)->findById($id);
        if (is_null($request)) {
            error(404);
        }
        $report = new Gazelle\Report\Request($request);
        break;

    case 'collage':
        $collage = (new Gazelle\Manager\Collage)->findById($id);
        if (is_null($collage)) {
            error(404);
        }
        $report = new Gazelle\Report\Collage($collage);
        break;

    case 'thread':
        $thread = (new Gazelle\Manager\ForumThread)->findById($id);
        if (is_null($thread)) {
            error(404);
        }
        if (!$Viewer->readAccess($thread->forum())) {
            error(403);
        }
        $report = new Gazelle\Report\ForumThread($thread);
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
        $report = (new Gazelle\Report\Comment($comment))->setContext($reportType['title']);
        break;

    default:
        error('Incorrect type');
        break;
}

echo $Twig->render('report/compose-reply.twig', [
    'report'  => $report,
    'user'    => $user,
    'viewer'  => $Viewer,
    'body'    => new Gazelle\Util\Textarea(
        'body',
        "You reported {$report->bbLink()} for the reason:\n[quote]{$report->reason()}[/quote]",
        90, 8
    ),
]);
