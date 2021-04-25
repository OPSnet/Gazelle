<?php
if (!check_perms('users_view_ips')) {
    error(403);
}

$manager = new Gazelle\Manager\DuplicateIP;
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($manager->total(IP_OVERLAPS));

View::show_header('Dupe IPs');
echo $Twig->render('admin/duplicate-ipaddr.twig', [
    'list'      => $manager->page(IP_OVERLAPS, $paginator->limit(), $paginator->offset()),
    'overlap'   => IP_OVERLAPS,
    'paginator' => $paginator,
]);
View::show_footer();
