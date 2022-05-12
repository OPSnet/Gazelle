<?php

if ($Viewer->disableForums()) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$user = empty($_GET['userid']) ? $Viewer : $userMan->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

$ownProfile = $user->id() === $Viewer->id();
$showUnread = $ownProfile && (bool)($_GET['showunread'] ?? false);
$showGrouped = $ownProfile && (bool)($_GET['group'] ?? false);

if ($showGrouped) {
    $title = 'Grouped '.($showUnread ? 'unread ' : '')."post history";
} elseif ($showUnread) {
    $title = "Unread post history";
} else {
    $title = "Post history";
}

$forumSearch = (new Gazelle\Search\Forum($user))
    ->setViewer($Viewer)
    ->setShowGrouped($showGrouped)
    ->setShowUnread($showUnread);

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($forumSearch->postHistoryTotal());

echo $Twig->render('user/post-history.twig', [
    'avatar'        => $userMan->avatarMarkup($Viewer, $user),
    'is_fmod'       => $Viewer->permitted('site_moderate_forums'),
    'own_profile'   => $ownProfile,
    'paginator'     => $paginator,
    'posts'         => $forumSearch->postHistoryPage($paginator->limit(), $paginator->offset()),
    'show_grouped'  => $showGrouped,
    'show_unread'   => $showUnread,
    'subscriptions' => (new \Gazelle\User\Subscription($user))->subscriptionList(),
    'title'         => $title,
    'url_stem'      => 'userhistory.php?action=posts&amp;userid=' . $user->id() . '&amp;',
    'user'          => $user,
    'viewer'        => $Viewer,
]);
