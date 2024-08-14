<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$name      = false;
$search    = false;
$similar   = false;
$result    = false;
$asnSearch = new Gazelle\Search\ASN();

if (isset($_REQUEST['name'])) {
    $name = trim($_REQUEST['name']);
    $search = $asnSearch->searchName($name);
    $similar = $asnSearch->similarName($name);
} elseif (isset($_REQUEST['asn'])) {
    $result = $asnSearch->findByASN((int)$_REQUEST['asn']);
    $name = $result['info']['name'];
}

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));

echo $Twig->render('admin/asn-search.twig', [
    'name'      => $name,
    'result'    => $result,
    'search'    => $search,
    'similar'   => $similar,
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
