<?php

if (!$Viewer->permitted('admin_view_referrals')) {
    error(403);
}

$ReferralManager = new Gazelle\Manager\Referral;

if (isset($_POST['id'])) {
    authorize();
    if (!$Viewer->permitted('admin_manage_referrals')) {
        error(403);
    }
    $ReferralManager->deleteUserReferral($_POST['id']);
}

$StartDate = $_GET['start_date'];
$EndDate   = $_GET['end_date'];
$Site      = $_GET['site'];
$Username  = $_GET['username'];
$Invite    = $_GET['invite'];

if (!empty($StartDate)) {
    [$Y, $M, $D] = array_map('intval', explode('-', $StartDate));
    if (!checkdate($M, $D, $Y)) {
        $StartDate = null;
    }
}

if (!empty($EndDate)) {
    [$Y, $M, $D] = array_map('intval', explode('-', $EndDate));
    if (!checkdate($M, $D, $Y)) {
        $EndDate = null;
    }
}

$View = $_GET['view'] ?? 'all';
$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));

echo $Twig->render('admin/referral-users.twig', [
    'invite'     => $Invite,
    'list'       => $ReferralManager->getReferredUsers($StartDate, $EndDate, $Site, $Username, $Invite, $paginator, $View),
    'paginator'  => $paginator,
    'site'       => $Site,
    'start_date' => $StartDate,
    'end_date'   => $EndDate,
    'username'   => $Username,
    'view'       => $View,
    'viewer'     => $Viewer,
]);
