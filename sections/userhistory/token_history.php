<?php

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = (new Gazelle\Manager\User())->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(0);
    }
    if ($user->id() !== $Viewer->id() && !$Viewer->permitted('admin_fl_history')) {
        error(403);
    }
}

$torMan = new Gazelle\Manager\Torrent();
$torMan->setViewer($Viewer);

if ($_GET['expire'] ?? 0) {
    if (!$Viewer->permitted('admin_fl_history')) {
        error(403);
    }
    $torrent = $torMan->findById((int)$_GET['torrentid']);
    if (is_null($torrent)) {
        error(404);
    }
    $torrent->expireToken($user->id());
    header("Location: userhistory.php?action=token_history&userid=" . $user->id());
}

$paginator = new Gazelle\Util\Paginator(25, (int)($_GET['page'] ?? 1));
$paginator->setTotal($user->stats()->flTokenTotal());

echo $Twig->render('user/history-freeleech.twig', [
    'admin'       => $Viewer->permitted('admin_fl_history'),
    'auth'        => $Viewer->auth(),
    'list'        => $user->tokenList($torMan, $paginator->limit(), $paginator->offset()),
    'own_profile' => $Viewer->id() == $user->id(),
    'paginator'   => $paginator,
    'user'        => $user,
]);
