<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$userMan    = new Gazelle\Manager\User;
$reportMan  = new Gazelle\Manager\ReportV2;
$Types      = $reportMan->types();
$categories = $reportMan->categories();

$reportNameCache = [];
foreach ($categories as $label => $key) {
    foreach (array_keys($Types[$label]) as $type) {
        $reportNameCache[$type] = $Types[$label][$type]['title'] . " ($key)";
    }
}

$filter = [];
if (isset($_GET['report-type'])) {
    foreach ($_GET['report-type'] as $t) {
        if (array_key_exists($t, $reportNameCache)) {
            $filter['report-type'][] = $t;
        }
    }
}

foreach(['reporter', 'handler', 'uploader'] as $role) {
    if (isset($_GET[$role]) && preg_match('/(@?[\w.-]+)/', $_GET[$role], $match)) {
        $user = $userMan->find($match[1]);
        if (is_null($user)) {
            error("No such $role: {$_GET[$role]} (numeric id or @username expected).");
        }
        $filter[$role] = $user;
    }
}
if (isset($_GET['torrent'])) {
    if (preg_match('/^\s*(\d+)\s*$/', $_GET['torrent'], $match)) {
        $filter['torrent'] = $match[1];
    } elseif (preg_match('#^https?://[^/]+/torrents\.php.*torrentid=(\d+)#', $_GET['torrent'], $match)) {
        $filter['torrent'] = $match[1];
    }
}
if (isset($_GET['group'])) {
    if (preg_match('/^\s*(\d+)\s*$/', $_GET['group'], $match)) {
        $filter['group'] = $match[1];
    } elseif (preg_match('#^https?://[^/]+/torrents\.php.*[?&]id=(\d+)#', $_GET['group'], $match)) {
        $filter['group'] = $match[1];
    }
}
if (isset($_GET['dt-from']) && preg_match('/(\d\d\d\d-\d\d-\d\d)/', $_GET['dt-from'], $match)) {
    $filter['dt-from'] = $match[1];
    $dtFrom = $match[1];
} else {
    $dtFrom  = date('Y-m-d', strtotime(date('Y-m-d', strtotime(date('Y-m-d'))) . '-1 month'));
}
if (isset($_GET['dt-until']) && preg_match('/(\d\d\d\d-\d\d-\d\d)/', $_GET['dt-until'], $match)) {
    $filter['dt-until'] = $match[1];
    $dtUntil = $match[1];
} else {
    $dtUntil = date('Y-m-d');
}

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));

if (!$filter) {
    $list = [];
} else {
    $reportMan->setSearchFilter($filter);
    $paginator->setTotal($reportMan->searchTotal());
    $list = $reportMan->searchList(new Gazelle\Manager\Torrent, $userMan, $paginator->limit(), $paginator->offset());
}

try {
echo $Twig->render('reportsv2/search.twig', [
    'paginator'   => $paginator,
    'list'        => $list,
    'dt_from'     => $dtFrom,
    'dt_until'    => $dtUntil,
    'name_cache'  => $reportNameCache,
    'report_type' => $_GET['report-type'] ?? [],
    'torrent_id'  => $_GET['torrent'] ?? null,
    'group_id'    => $_GET['group'] ?? null,
    'handler'     => $_GET['handler'] ?? null,
    'reporter'    => $_GET['reporter'] ?? null,
    'uploader'    => $_GET['uploader'] ?? null,
]);
} catch (\Exception $e) {
echo $e->getMessage();
}
