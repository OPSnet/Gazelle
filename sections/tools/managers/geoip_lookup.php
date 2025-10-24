<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$ipMatches = null;
$geoipData = null;
if (isset($_POST['data'])) {
    $ipMatches = UserMatch\ListMatcher::extract($_POST['data']);
    if (!$ipMatches) {
        Error400::error('No valid IP addresses found.');
    }
    $uniqueIps = [];
    foreach ($ipMatches as $match) {
        $uniqueIps[$match[0]] = true;
    }
    $uniqueIps = array_keys($uniqueIps);
    $GeoIP = new Util\GeoIP();
    $geoipData = $GeoIP->lookupList($uniqueIps);
}

echo $Twig->render('admin/geoip-lookup.twig', [
    'geoip_data' => $geoipData,
    'ip_matches' => $ipMatches,
]);
