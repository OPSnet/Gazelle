<?php
$forum = (new Gazelle\Manager\Forum)->findById((int)($_GET['forumid'] ?? 0));
if (!$forum) {
    error(404);
}
if (!Forums::check_forumperm($forum->id(), 'Write') || !Forums::check_forumperm($forum->id(), 'Create')) {
    error(403);
}
$info = $forum->info();

View::show_header('Forums &rsaquo; ' . $info['name'] . ' &rsaquo; New Thread', 'comments,bbcode,jquery.validate,form_validate,newpoll');
echo G::$Twig->render('forum/new-thread.twig', [
    'can' => [
        'create_poll' => check_perms('forums_polls_create'),
        'see_avatars' => Users::has_avatars_enabled(),
    ],
    'auth'      => $LoggedUser['AuthKey'],
    'avatar'    => Users::show_avatar($LoggedUser['Avatar'], $LoggedUser['ID'], $LoggedUser['Username'], $HeavyInfo['DisableAvatars']),
    'id'        => $forum->id(),
    'is_subbed' => !empty($HeavyInfo['AutoSubscribe']),
    'name'      => $info['name'],
    'user_id'   => $LoggedUser['ID'],
]);
View::show_footer();
