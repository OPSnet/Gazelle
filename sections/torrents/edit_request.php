<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_GET['id'] ?? 0));
if (!$tgroup) {
    error(404);
}

echo $Twig->render('torrent/edit-request.twig', [
    'textarea' => new Gazelle\Util\Textarea('edit_details', ''),
    'tgroup'   => $tgroup,
    'viewer'   => $Viewer,
]);
