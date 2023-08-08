<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

$extend = array_map(
    fn($x) => intval(explode('-', $x)[1]),
    array_keys(
        array_filter(
            $_POST,
            fn($x) => preg_match('/^extend-\d+$/', $x), ARRAY_FILTER_USE_KEY
        )
    )
);

$affected = false;
$reaper   = new Gazelle\Torrent\Reaper(new Gazelle\Manager\Torrent, new Gazelle\Manager\User);
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
