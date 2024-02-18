<?php

use Gazelle\User\Vote;

$user = (new Gazelle\Manager\User())->findById((int)$_GET['id']);
if (is_null($user)) {
    error(404);
}
$ownProfile = $user->id() === $Viewer->id();
if (!$Viewer->permitted('view-release-votes') && !$ownProfile) {
    error(403);
}

if (isset($_GET['up'])) {
    $mask = Vote::UPVOTE;
} elseif (isset($_GET['down'])) {
    $mask = Vote::DOWNVOTE;
} else {
    $mask = Vote::UPVOTE | Vote::DOWNVOTE;
}

$vote = new Vote($user);
$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($vote->userTotal($mask));

echo $Twig->render('user/vote-history.twig', [
    'page'      => $vote->userPage(new Gazelle\Manager\TGroup(), $mask, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'show_down' => isset($_GET['down']),
    'show_up'   => isset($_GET['up']),
    'user'      => $user,
]);
