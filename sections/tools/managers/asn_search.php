<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$name      = false;
$search    = false;
$similar   = false;
$result    = false;
$asnSearch = new Search\ASN();

if (isset($_REQUEST['name'])) {
    $name = trim($_REQUEST['name']);
    $search = $asnSearch->searchName($name);
    $similar = $asnSearch->similarName($name);
} elseif (isset($_REQUEST['asn'])) {
    $result = $asnSearch->findByASN((int)$_REQUEST['asn']);
    $name = $result['info']['name'];
}

$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));

echo $Twig->render('admin/asn-search.twig', [
    'name'      => $name,
    'result'    => $result,
    'search'    => $search,
    'similar'   => $similar,
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
