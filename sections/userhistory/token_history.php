<?php

if (!isset($_GET['userid'])) {
    $userId = $LoggedUser['ID'];
} else {
    $userId = (int)$_GET['userid'];
    if (!$userId) {
        error(0);
    }
    if ($userId !== $LoggedUser['ID'] && !check_perms('admin_fl_history')) {
        error(403);
    }
}

$user = (new Gazelle\Manager\User)->findById($userId);
if (!$user) {
    error(404);
}

$torMan = new Gazelle\Manager\Torrent;
if ($_GET['expire'] ?? 0) {
    if (!check_perms('admin_fl_history')) {
        error(403);
    }
    $torrentId = (int)$_GET['torrentid'];
    if (!$torrentId) {
        error(404);
    }
    $torMan->expireToken($userId, $torrentId);
    header("Location: userhistory.php?action=token_history&userid=$userId");
}

$paginator = new Gazelle\Util\Paginator(25, (int)($_GET['page'] ?? 1));
$paginator->setTotal((new Gazelle\Stats\User)->flTokenTotal($user));

View::show_header($user->username() . ' &rsaquo; Freeleech token history');

$user->setTorrentManager($torMan)
    ->setTorrentLabelManager(
        (new Gazelle\Manager\TorrentLabel)->showMedia(true)->showEdition(true)
    );

echo $Twig->render('user/history-freeleech.twig', [
    'admin'       => check_perms('admin_fl_history'),
    'auth'        => $LoggedUser['AuthKey'],
    'own_profile' => $LoggedUser['ID'] == $userId,
    'paginator'   => $paginator,
    'user'        => $user,
]);

View::show_footer();
