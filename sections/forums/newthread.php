<?php
$forum = (new Gazelle\Manager\Forum)->findById((int)($_GET['forumid'] ?? 0));
if (!$forum) {
    error(404);
}
if (!$Viewer->writeAccess($forum) || !$Viewer->createAccess($forum)) {
    error(403);
}
$userMan = new Gazelle\Manager\User;

View::show_header('Forums &rsaquo; ' . $forum->name() . ' &rsaquo; New Thread', 'comments,bbcode,jquery.validate,form_validate,newpoll');
echo $Twig->render('forum/new-thread.twig', [
    'can' => [
        'create_poll' => $Viewer->permitted('forums_polls_create'),
        'see_avatars' => $Viewer->showAvatars(),
    ],
    'auth'      => $Viewer->auth(),
    'avatar'    => $userMan->avatarMarkup($Viewer, $Viewer),
    'id'        => $forum->id(),
    'is_subbed' => $Viewer->option('AutoSubscribe'),
    'name'      => $forum->name(),
    'user_id'   => $Viewer->id(),
]);
View::show_footer();
