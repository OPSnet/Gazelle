<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$torMan  = new Manager\Torrent();
$id      = (int)($_GET['id'] ?? 0);
$torrent = $torMan->findById($id);
if (is_null($torrent)) {
    header("Location: log.php?search=Torrent+$id");
    return;
}

echo $Twig->render('reportsv2/create.twig', [
    'post' => [
        'extra'    => $_POST['extra'] ?? '',
        'image'    => $_POST['image'] ?? '',
        'link'     => $_POST['link'] ?? '',
        'sitelink' => $_POST['sitelink'] ?? '',
        'track'    => $_POST['track'] ?? '',
    ],
    'report_man' => new Manager\Torrent\Report($torMan),
    'rtype_list' => new Manager\Torrent\ReportType()
        ->categoryList($torrent->group()->categoryId()),
    'torrent'    => $torrent,
    'tor_man'    => $torMan,
    'url_stem'   => new User\Stylesheet($Viewer)->imagePath(),
    'viewer'     => $Viewer,
]);
