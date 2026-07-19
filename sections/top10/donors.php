<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$limit = (int)($_GET['limit'] ?? 10);
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

echo $Twig->render('top10/donor.twig', [
    'is_mod' => $Viewer->permitted("users_mod"),
    'limit'  => $limit,
    'list'   => new Manager\Donation()->topDonorList($limit, new Manager\User()),
]);
