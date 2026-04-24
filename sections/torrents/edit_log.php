<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$torrent = new Manager\Torrent()->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error404::error();
}
$riplog = new Manager\Riplog()->findById($torrent, (int)($_GET['logid'] ?? 0));
if (is_null($riplog)) {
    Error404::error();
}

echo $Twig->render('riplog/edit.twig', [
    'riplog'  => $riplog,
]);
