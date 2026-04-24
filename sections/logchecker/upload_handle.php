<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

use OrpheusNET\Logchecker\Logchecker;

ini_set('upload_max_filesize', 1_000_000);

$torrent = new Manager\Torrent()->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    Error404::error('No torrent is selected.');
}
if ($torrent->media() !== 'CD') {
    Error400::error('Media of torrent precludes adding a log.');
}
if ($torrent->uploaderId() != $Viewer->id && !$Viewer->permitted('admin_add_log')) {
    Error403::error('Not your upload.');
}

$manager = new Manager\Riplog();
$riplogList = $manager->bulkCreate($torrent, $_FILES['logfiles']);
if ($riplogList === []) {
    Error400::error("No (new) logfiles uploaded.");
}
$manager->refreshTorrent($torrent);

echo $Twig->render('riplog/logchecker-result.twig', [
    'action' => in_array($_POST['from_action'], ['upload', 'update'])
        ? $_POST['from_action']
        : 'upload',
    'list'   => $riplogList,
    'viewer' => $Viewer,
]);
