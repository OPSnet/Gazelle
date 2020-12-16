<?php

$ForumID = (int)$_GET['forumid'];
if (!$ForumID) {
    error(404);
}
$Forum = Forums::get_forum_info($ForumID);
if ($Forum === false) {
    error(404);
}
if (!Forums::check_forumperm($ForumID, 'Write') || !Forums::check_forumperm($ForumID, 'Create')) {
    error(403);
}

View::show_header('Forums &rsaquo; '.$Forum['Name'].' &rsaquo; New Thread', 'comments,bbcode,jquery.validate,form_validate,newpoll');
echo G::$Twig->render('forum/new-thread.twig', [
    'can' => [
        'create_poll' => check_perms('forums_polls_create'),
        'see_avatars' => Users::has_avatars_enabled(),
    ],
    'auth'      => $LoggedUser['AuthKey'],
    'avatar'    => Users::show_avatar($LoggedUser['Avatar'], $LoggedUser['ID'], $LoggedUser['Username'], $HeavyInfo['DisableAvatars']),
    'forum'     => $Forum,
    'forum_id'  => $ForumID,
    'is_subbed' => !empty($HeavyInfo['AutoSubscribe']),
    'user_id'   => $LoggedUser['ID'],
]);
View::show_footer();
