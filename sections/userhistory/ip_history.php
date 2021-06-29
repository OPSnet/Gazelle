<?php

use Gazelle\Util\Time;

if (!check_perms('users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}
$ipMan = new Gazelle\Manager\IPv4;
if (trim($_GET['ip'] ?? '') !== '') {
    $ipMan->setFilterIpaddrRegexp(trim($_GET['ip']));
}

$paginator = new Gazelle\Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($ipMan->userTotal($user->id()));

View::show_header($user->username() . " &rsaquo; IP address history", 'iphist');
echo $Twig->render('admin/userhistory-site-ip.twig', [
    'ip'        => $_GET['ip'] ?? '',
    'page'      => $ipMan->userPage($user->id(), $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'user'      => $user,
]);
View::show_footer();
