<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disablePosting()) {
    Error403::error("Your posting privileges have been removed.");
}
authorize();

$post = new Manager\ForumPost()->findById((int)($_POST['post'] ?? 0));
if (!$post) {
    Error404::error(display_str("No forum post #{$_POST['post']} found"));
}
$thread = $post->thread();
if (!$Viewer->writeAccess($thread->forum())) {
    Error403::error("You lack the permission to edit this post.");
}

if ($thread->isLocked() && !$Viewer->permitted('site_moderate_forums')) {
    Error403::error("You cannot edit a post in a locked thread.");
}
if ($Viewer->id != $post->userId()) {
    if (!$Viewer->permitted('site_moderate_forums')) {
        Error403::error("You cannot edit someone else's post");
    }
    if ($_POST['pm'] ?? 0) {
        $user = new Manager\User()->findById($post->userId());
        if (is_null($user)) {
            Error404::error('Author of post not found');
        }
        $user->inbox()->createSystem(
            "Your post #{$post->id} has been edited",
            "One of your posts has been edited by [url={$Viewer->url()}]{$Viewer->username()}[/url]: [url]{$post->url()}[/url]"
        );
    }
}

$post->edit($Viewer, trim($_POST['body']));

// This gets sent to the browser, which echoes it in place of the old body
echo \Text::full_format($post->body());
?>
<br><br><span class="last_edited">Last edited by <?= $Viewer->link() ?> Just now</span>
