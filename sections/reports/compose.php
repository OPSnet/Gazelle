<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$reportId = (int)($_GET['reportid'] ?? 0);
$id       = (int)($_GET['thingid'] ?? 0);
$type     = $_GET['type'] ?? null;
if (!$reportId || !$id || is_null($type)) {
    error(403);
}

require_once('array.php');
/** @var array $Types */
$reportType = $Types[$type];

$user = null;
if (!isset($Return)) {
    $user = (new Gazelle\Manager\User())->findById((int)($_GET['toid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
    if ($user->id() === $Viewer->id()) {
        error("You cannot start a conversation with yourself!");
    }
}

switch ($type) {
    case 'user':
        $reported = (new Gazelle\Manager\User())->findById($id);
        if (is_null($reported)) {
            error(404);
        }
        $report = new Gazelle\Report\User($reportId, $reported);
        break;

    case 'request':
    case 'request_update':
        $request = (new Gazelle\Manager\Request())->findById($id);
        if (is_null($request)) {
            error(404);
        }
        $report = new Gazelle\Report\Request($reportId, $request);
        break;

    case 'collage':
        $collage = (new Gazelle\Manager\Collage())->findById($id);
        if (is_null($collage)) {
            error(404);
        }
        $report = new Gazelle\Report\Collage($reportId, $collage);
        break;

    case 'thread':
        $thread = (new Gazelle\Manager\ForumThread())->findById($id);
        if (is_null($thread)) {
            error(404);
        }
        if (!$Viewer->readAccess($thread->forum())) {
            error(403);
        }
        $report = new Gazelle\Report\ForumThread($reportId, $thread);
        break;

    case 'post':
        $post = (new Gazelle\Manager\ForumPost())->findById($id);
        if (is_null($post)) {
            error(404);
        }
        if (!$Viewer->readAccess($post->thread()->forum())) {
            error(403);
        }
        $report = new Gazelle\Report\ForumPost($reportId, $post);
        break;

    case 'comment':
        $comment = (new Gazelle\Manager\Comment())->findById($id);
        if (is_null($comment)) {
            error(404);
        }
        $report = (new Gazelle\Report\Comment($reportId, $comment))->setContext($reportType['title']);
        break;

    default:
        error('Incorrect type');
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
