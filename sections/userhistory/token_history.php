<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = new Manager\User()->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
    if ($user->id !== $Viewer->id() && !($Viewer->isFLS() || $Viewer->permitted('admin_fl_history'))) {
        Error403::error();
    }
}

$torMan = new Manager\Torrent();

if ($_GET['expire'] ?? 0) {
    if (!$Viewer->permitted('admin_fl_history')) {
        Error403::error();
    }
    $torrent = $torMan->findById((int)$_GET['torrentid']);
    if (is_null($torrent)) {
        Error404::error();
    }
    $torrent->expireToken($user);
    header("Location: userhistory.php?action=token_history&userid=" . $user->id);
}

$paginator = new Util\Paginator(25, (int)($_GET['page'] ?? 1));
$paginator->setTotal($user->stats()->flTokenTotal());

echo $Twig->render('user/history-freeleech.twig', [
    'list'      => $user->tokenList($torMan, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'user'      => $user,
    'viewer'    => $Viewer,
]);
