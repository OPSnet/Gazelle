<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}

$reportId = (int)($_GET['reportid'] ?? 0);
$id       = (int)($_GET['thingid'] ?? 0);
$type     = $_GET['type'] ?? null;
if (!$reportId || !$id || is_null($type)) {
    Error400::error();
}

require __DIR__ . '/array.php';
/** @var array $Types */
$reportType = $Types[$type];

$user = null;
if (!isset($Return)) {
    $user = new Manager\User()->findById((int)($_GET['toid'] ?? 0));
    if (is_null($user)) {
        Error404::error();
    }
    if ($user->id === $Viewer->id) {
        Error400::error("You cannot start a conversation with yourself!");
    }
}

switch ($type) {
    case 'user':
        $reported = new Manager\User()->findById($id);
        if (is_null($reported)) {
            Error404::error();
        }
        $report = new Report\User($reportId, $reported);
        break;

    case 'request':
        $request = new Manager\Request()->findById($id);
        if (is_null($request)) {
            Error404::error();
        }
        $report = new Report\Request($reportId, $request);
        break;

    case 'collage':
        $collage = new Manager\Collage()->findById($id);
        if (is_null($collage)) {
            Error404::error();
        }
        $report = new Report\Collage($reportId, $collage);
        break;

    case 'thread':
        $thread = new Manager\ForumThread()->findById($id);
        if (is_null($thread)) {
            Error404::error();
        }
        if (!$Viewer->readAccess($thread->forum())) {
            Error403::error();
        }
        $report = new Report\ForumThread($reportId, $thread);
        break;

    case 'post':
        $post = new Manager\ForumPost()->findById($id);
        if (is_null($post)) {
            Error404::error();
        }
        if (!$Viewer->readAccess($post->thread()->forum())) {
            Error403::error();
        }
        $report = new Report\ForumPost($reportId, $post);
        break;

    case 'comment':
        $comment = new Manager\Comment()->findById($id);
        if (is_null($comment)) {
            Error404::error();
        }
        $report = new Report\Comment($reportId, $comment)->setContext($reportType['title']);
        break;

    default:
        Error400::error('Incorrect type');
}

echo $Twig->render('report/compose-reply.twig', [
    'report'  => $report,
    'user'    => $user,
    'viewer'  => $Viewer,
    'body'    => new Util\Textarea(
        'body',
        "You reported {$report->bbLink()} for the reason:\n[quote]{$report->reason()}[/quote]",
        90, 8
    ),
]);
