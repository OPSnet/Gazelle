<?php

if (!$Viewer->disableForums()) {
    error(403);
}
$userMan = new Gazelle\Manager\User;
$user = empty($_GET['userid']) ? $Viewer : $userMan->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

$ownProfile = $user->id() === $Viewer->id();
$showUnread = ($ownProfile && (!isset($_GET['showunread']) || !!$_GET['showunread']));
$showGrouped = ($ownProfile && (!isset($_GET['group']) || !!$_GET['group']));

if ($showGrouped) {
    $title = 'Grouped '.($showUnread ? 'unread ' : '')."post history";
} elseif ($showUnread) {
    $title = "Unread post history";
} else {
    $title = "Post history";
}

$forumSearch = (new Gazelle\ForumSearch($Viewer))
    ->setPosterId($user->id())
    ->setShowGrouped($ownProfile && $showGrouped)
    ->setShowUnread($ownProfile && $showUnread);

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($forumSearch->postHistoryTotal());

View::show_header($user->username() . " &rsaquo; $title", 'subscriptions,comments,bbcode');

echo $Twig->render('user/post-history.twig', [
    'avatar'        => $userMan->avatarMarkup($Viewer, $user),
    'is_fmod'       => check_perms('site_moderate_forums'),
    'own_profile'   => $ownProfile,
    'paginator'     => $paginator,
    'posts'         => $forumSearch->postHistoryPage($paginator->limit(), $paginator->offset()),
    'show_grouped'  => $showGrouped,
    'show_unread'   => $showUnread,
    'subscriptions' => (new \Gazelle\Manager\Subscription($user->id()))->subscriptions(),
    'title'         => $title,
    'url_stem'      => 'userhistory.php?action=posts&amp;userid=' . $user->id() . '&amp;',
    'user'          => $user,
    'viewer'        => $Viewer,
]);

View::show_footer();
