<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_view_referrals')) {
    Error403::error();
}

$manager = new Manager\Referral();

if (isset($_POST['id'])) {
    authorize();
    if (!$Viewer->permitted('admin_manage_referrals')) {
        Error403::error();
    }
    $manager->deleteUserReferral($_POST['id']);
}

$endDate   = $_GET['end_date'];
$startDate = $_GET['start_date'];
$site      = $_GET['site'];
$username  = $_GET['username'];
$invite    = $_GET['invite'];
$view      = $_GET['view'] ?? 'all';
$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));

echo $Twig->render('admin/referral-users.twig', [
    'invite'     => $invite,
    'list'       => $manager->getReferredUsers($startDate, $endDate, $site, $username, $invite, $paginator, $view),
    'paginator'  => $paginator,
    'site'       => $site,
    'start_date' => $startDate,
    'end_date'   => $endDate,
    'username'   => $username,
    'view'       => $view,
    'viewer'     => $Viewer,
]);
