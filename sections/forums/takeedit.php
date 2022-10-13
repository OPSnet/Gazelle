<?php

if ($Viewer->disablePosting()) {
    error("Your posting privileges have been removed.", true);
}
authorize();

$post = (new Gazelle\Manager\ForumPost)->findById((int)($_POST['post'] ?? 0));
if (!$post) {
    error("No forum post #{$_POST['post']} found", true);
}
$thread = $post->thread();
if (!$Viewer->writeAccess($thread->forum())) {
    error("You lack the permission to edit this post.", true);
}

if ($thread->isLocked() && !$Viewer->permitted('site_moderate_forums')) {
    error("You cannot edit a post in a locked thread.", true);
}
if ($Viewer->id() != $post->userId()) {
    if (!$Viewer->permitted('site_moderate_forums')) {
        error("You cannot edit someone else's post", true);
    }
    if ($_POST['pm'] ?? 0) {
        (new Gazelle\Manager\User)->sendPM($post->userId(), 0,
            "Your post #{$post->id()} has been edited",
            sprintf('One of your posts has been edited by [url=%s]%s[/url]: [url]%s[/url]',
                $Viewer->url(), $Viewer->username(), $post->url()
            )
        );
    }
}

$post->edit($Viewer->id(), trim($_POST['body']));

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($post->body());
?>
<br /><br /><span class="last_edited">Last edited by <?= $Viewer->link() ?> Just now</span>
