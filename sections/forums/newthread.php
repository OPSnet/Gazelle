<?php
$forum = (new Gazelle\Manager\Forum)->findById((int)($_GET['forumid'] ?? 0));
if (!$forum) {
    error(404);
}
if (!Forums::check_forumperm($forum->id(), 'Write') || !Forums::check_forumperm($forum->id(), 'Create')) {
    error(403);
}
$info = $forum->info();
$userMan = new Gazelle\Manager\User;
$user = $userMan->findById($LoggedUser['ID']);

View::show_header('Forums &rsaquo; ' . $info['name'] . ' &rsaquo; New Thread', 'comments,bbcode,jquery.validate,form_validate,newpoll');
echo G::$Twig->render('forum/new-thread.twig', [
    'can' => [
        'create_poll' => check_perms('forums_polls_create'),
        'see_avatars' => $user->showAvatars(),
    ],
    'auth'      => $LoggedUser['AuthKey'],
    'avatar'    => $userMan->avatarMarkup($user, $user),
    'id'        => $forum->id(),
    'is_subbed' => !empty($HeavyInfo['AutoSubscribe']),
    'name'      => $info['name'],
    'user_id'   => $LoggedUser['ID'],
]);
View::show_footer();
