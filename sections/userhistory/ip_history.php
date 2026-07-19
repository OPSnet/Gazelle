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

$user = new Manager\User()->findById((int)$_GET['userid']);
if (is_null($user)) {
    Error404::error();
}
$ipMan = new Manager\IPv4();
if (trim($_GET['ip'] ?? '') !== '') {
    $ipMan->setFilterIpaddrRegexp(trim($_GET['ip']));
}

$paginator = new Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($ipMan->userTotal($user));

echo $Twig->render('admin/userhistory-site-ip.twig', [
    'ip'        => $_GET['ip'] ?? '',
    'page'      => $ipMan->userPage($user, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'user'      => $user,
]);
