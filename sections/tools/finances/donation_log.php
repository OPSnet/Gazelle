<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_donor_log')) {
    Error403::error();
}

$search = new Search\Donation();
if (!empty($_GET['username'])) {
    $search->setUsername($_GET['username']);
}
if (!empty($_GET['after_date']) && !empty($_GET['before_date'])) {
    $search->setInterval($_GET['after_date'], $_GET['before_date']);
}

$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$donorMan = new Manager\Donation();
$timeline = $donorMan->timeline();

echo $Twig->render('admin/donation-log.twig', [
    'after'       => $_GET['after_date'] ?? date('Y-m-d', (int)date('U') - (int)(86400 * 365.25)),
    'before'      => $_GET['before_date'] ?? date('Y-m-d'),
    'amount'      => array_column($timeline, 'Amount'),
    'month'       => array_column($timeline, 'Month'),
    'grand_total' => $donorMan->grandTotal(),
    'page'        => $search->page($paginator->limit(), $paginator->offset()),
    'paginator'   => $paginator,
    'rental'      => new Manager\Payment()->monthlyRental(),
    'username'    => $_GET['username'] ?? '',
]);
