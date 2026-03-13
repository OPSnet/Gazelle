<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$id = (int)$_GET['id'];
if (!$id) {
    Error404::error();
}

require __DIR__ . '/array.php';
if (!isset($Types[$_GET['type'] ?? ''])) {
    Error403::error();
}
$type = $_GET['type'];
$reportType = $Types[$type];

switch ($type) {
    case 'user':
        $user = new Manager\User()->findById($id);
        if (is_null($user)) {
            Error404::error();
        }
        $report = new Report\User($id, $user);
        break;

    case 'request':
        $request = new Manager\Request()->findById($id);
        if (is_null($request)) {
            Error404::error();
        }
        $report = new Report\Request($id, $request);
        break;

    case 'collage':
        $collage = new Manager\Collage()->findById($id);
        if (is_null($collage)) {
            Error404::error();
        }
        $report = new Report\Collage($id, $collage);
        break;

    case 'thread':
        $thread = new Manager\ForumThread()->findById($id);
        if (is_null($thread)) {
            Error404::error();
        }
        if (!$Viewer->readAccess($thread->forum())) {
            Error403::error();
        }
        $report = new Report\ForumThread($id, $thread);
        break;

    case 'post':
        $post = new Manager\ForumPost()->findById($id);
        if (is_null($post)) {
            Error404::error();
        }
        if (!$Viewer->readAccess($post->thread()->forum())) {
            Error403::error();
        }
        $report = new Report\ForumPost($id, $post);
        break;

    case 'comment':
        $comment = new Manager\Comment()->findById($id);
        if (is_null($comment)) {
            Error404::error();
        }
        $report = new Report\Comment($id, $comment)->setContext($reportType['title']);
        break;
    default:
        Error400::error('Unknown report target');
}

echo $Twig->render('report/create.twig', [
    'id'          => $id,
    'release'     => new ReleaseType()->list(),
    'report'      => $report,
    'report_type' => $reportType,
    'type'        => $type,
    'viewer'      => $Viewer,
]);
