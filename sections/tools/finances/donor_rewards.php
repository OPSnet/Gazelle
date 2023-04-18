<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$manager = new Gazelle\Manager\Donation;
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($manager->rewardTotal());
$search = $_GET['search'] ?? null;

echo $Twig->render('donation/reward-list.twig', [
    'paginator' => $paginator,
    'user'      => $manager->rewardPage($search, $paginator->limit(), $paginator->offset()),
    'search'    => $search,
]);
