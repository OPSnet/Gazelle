<?php

if (!$Viewer->permitted('site_moderate_requests')) {
    error(403);
}
$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

echo $Twig->render('torrent/masspm.twig', [
    'auth'     => $Viewer->auth(),
    'textarea' => new Gazelle\Util\Textarea('message', '[pl]' . $torrent->id() . '[/pl]', 60, 8),
    'torrent'  => $torrent,
]);
