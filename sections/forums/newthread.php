<?php
$forum = (new Gazelle\Manager\Forum)->findById((int)($_GET['forumid'] ?? 0));
if (!$forum) {
    error(404);
}
$userMan = new Gazelle\Manager\User;
$user = $userMan->findById($LoggedUser['ID']);
if (!$user->writeAccess($forum) || !$user->createAccess($forum)) {
    error(403);
}

View::show_header('Forums &rsaquo; ' . $forum->name() . ' &rsaquo; New Thread', 'comments,bbcode,jquery.validate,form_validate,newpoll');
echo $Twig->render('forum/new-thread.twig', [
    'can' => [
        'create_poll' => check_perms('forums_polls_create'),
        'see_avatars' => $user->showAvatars(),
    ],
    'auth'      => $LoggedUser['AuthKey'],
    'avatar'    => $userMan->avatarMarkup($user, $user),
    'id'        => $forum->id(),
    'is_subbed' => $user->option('AutoSubscribe'),
    'name'      => $forum->name(),
    'user_id'   => $LoggedUser['ID'],
]);
View::show_footer();
