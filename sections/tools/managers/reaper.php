<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_view_flow')) {
    Error403::error();
}

$affected = false;
$reaper   = new Torrent\Reaper(new Manager\Torrent(), new Manager\User());

$extend = array_key_extract_suffix('extend-', $_POST);
if ($extend) {
    authorize();
    $affected = $reaper->extendGracePeriod($extend, (int)$_POST['extension']);
}

// First time the page is rendered, only "unseeded" is checked, which
// is usually what is wanted. Subsequent page refreshes will persist
// what was checked on the previous render.
$never    = isset($_POST['never']);
$unseeded = !isset($_POST['extension']) || isset($_POST['unseeded']);

echo $Twig->render('admin/reaper.twig', [
    'affected' => $affected,
    'list'     => $reaper->unseederList($never, $unseeded),
    'never'    => $never,
    'unseeded' => $unseeded,
    'viewer'   => $Viewer,
]);
