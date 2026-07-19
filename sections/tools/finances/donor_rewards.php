<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$manager = new Manager\Donation();
$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($manager->rewardTotal());
$search = $_GET['search'] ?? null;

echo $Twig->render('donation/reward-list.twig', [
    'paginator' => $paginator,
    'user'      => $manager->rewardPage($search, $paginator->limit(), $paginator->offset()),
    'search'    => $search,
]);
