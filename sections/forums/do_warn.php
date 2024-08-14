<?php
/** @phpstan-var \Gazelle\User $Viewer */

// this file is used by sections/comments/take_warn.php and sections/forums/take_warn.php
// @phpstan-ignore-next-line return type has no value type specified in iterable type array
function handleWarningRequest(\Gazelle\Manager\ForumPost|\Gazelle\Manager\Comment $manager): array {
    global $Viewer;

    if (!$Viewer->permitted('users_warn')) {
        error(403);
    }
    authorize();

    $postId = (int)($_POST['postid'] ?? 0);
    $post = $manager->findById($postId);
    if (is_null($post)) {
        error(404);
    }

    $userMan = new \Gazelle\Manager\User();
    $user = $userMan->findById($post->userId());
    if (is_null($user)) {
        error(404);
    }
    if ($user->classLevel() >= $Viewer->classLevel()) {
        error(403);
    }

    $body = trim($_POST['body'] ?? '');
    if (empty($body)) {
        error("Post body cannot be left empty (you can leave a reason for others to see)");
    }
    if (empty($_POST['reason'])) {
        error("Reason for warning not provided");
    }
    if (!isset($_POST['length']) || !strlen($_POST['length'])) {
        error("Length of warning not provided");
    }

    $weeks = (int)$_POST['length'];
    $reason = trim($_POST['reason']);
    $userMessage = trim($_POST['privatemessage']);

    $user->warnPost($post, $weeks, $Viewer, $reason, $userMessage);

    header("Location: {$post->location()}");

    return [$post, $body];
}
