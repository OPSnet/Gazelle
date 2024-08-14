<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$manager = new Gazelle\Manager\DuplicateIP();
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($manager->total(IP_OVERLAPS));

echo $Twig->render('admin/duplicate-ipaddr.twig', [
    'list'      => $manager->page(IP_OVERLAPS, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
]);
