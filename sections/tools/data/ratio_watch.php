<?php
if (!check_perms('site_view_flow')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));

$Results = $userMan->totalRatioWatchUsers();
$Pages = Format::get_pages($paginator->page(), $Results, $paginator->limit());

$TotalDisabled = $userMan->totalBannedForRatio();
$Users = $userMan->ratioWatchUsers($paginator->limit(), $paginator->offset());

View::show_header('Ratio Watch');

echo G::$Twig->render('admin/ratio-watch.twig', [
    'total'          => $Results,
    'total_disabled' => $TotalDisabled,
    'linkbox'        => $Pages,
    'users'          => $Users,
]);
View::show_footer();
