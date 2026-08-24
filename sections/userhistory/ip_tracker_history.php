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

$user = new Manager\User()->findById((int)($_GET['userid'] ?? 0));
$ipaddr = $_GET['ip'] ?? null;
if (is_null($user) && !preg_match(IP_REGEXP, $ipaddr)) {
    Error403::error();
}

$snatchInfo = new SnatchInfo();
if ($user) {
    $snatchInfo->setContextUser($user);
} else {
    $snatchInfo->setContextIpaddr($ipaddr);
}

$paginator = new Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($snatchInfo->total());

$summary = $snatchInfo->summary();
$geoip   = new Search\ASN()->findByIpList(array_map(fn ($e) => $e['IP'], $summary));
echo $Twig->render('admin/history-ip-tracker.twig', [
    'details'    => $snatchInfo->page($paginator->limit(), $paginator->offset()),
    'geoip_data' => $geoip,
    'ipaddr'     => $ipaddr,
    'paginator'  => $paginator,
    'summary'    => $summary,
    'user'       => $user,
]);
