<?php

if (!$Viewer->permittedAny('users_mod', 'users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
$ipaddr = $_GET['ip'] ?? null;
if (is_null($user) && !preg_match(IP_REGEXP, $ipaddr)) {
    error(403);
}

$snatchInfo = new Gazelle\SnatchInfo;
if ($user) {
    $snatchInfo->setContextUser($user);
} else {
    $snatchInfo->setContextIpaddr($ipaddr);
}

$paginator = new Gazelle\Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($snatchInfo->total());

echo $Twig->render('admin/history-ip-tracker.twig', [
    'details'   => $snatchInfo->page($paginator->limit(), $paginator->offset()),
    'ipaddr'    => $ipaddr,
    'is_mod'    => $Viewer->permitted('users_mod'),
    'paginator' => $paginator,
    'summary'   => $snatchInfo->summary(),
    'urlstem'   => $_SERVER['SCRIPT_NAME'] . '?action=tracker_ips&',
    'user'      => $user,
]);
