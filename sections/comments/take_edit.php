<?php

authorize();
if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.', true);
}

$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    error(404, true);
}

try {
    $comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
} catch (\Gazelle\Exception\ResourceNotFoundException $e) {
    error(404, true);
}
if ($comment->userId() != $LoggedUser['ID'] && !check_perms('site_moderate_forums')) {
    error(403, true);
}

$comment->setBody($body)->setEditedUserID($LoggedUser['ID'])->modify();
if ((bool)($_POST['pm'] ?? false) && !$comment->isAuthor($LoggedUser['ID'])) {
    // Send a PM to the user to notify them of the edit
    $id = $comment->id();
    $url = SITE_URL . "/comments.php?action=jump&postid=$id";
    $moderator = "[url=user.php?id={$LoggedUser['ID']}]{$LoggedUser['Username']}[/url]";
    (new Gazelle\Manager\User)-> sendPM($comment->userId(), 0,
        "Your comment #$id has been edited",
        "One of your comments has been edited by $moderator: [url]{$url}[/url]"
    );
}

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($body);
