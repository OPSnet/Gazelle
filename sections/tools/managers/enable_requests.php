<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

if (!FEATURE_EMAIL_REENABLE) {
    error("Email reenabling is currently switched off");
}

$showChecked = $_GET['show_checked'] ?? false;
$enableMan = new Gazelle\Manager\AutoEnable();
$enableMan->configureView($_GET['view'] ?? '', $showChecked);

// Build query further based on search
if (isset($_GET['search'])) {
    $username = trim($_GET['username']);
    if (!empty($username)) {
        $enableMan->filterUsername($username);
    }

    $admin = trim($_GET['handled_username']);
    if (!empty($admin)) {
        $enableMan->filterAdmin($admin);
    }
}

$heading = new Gazelle\Util\SortableTableHeader('submitted_timestamp', [
    'submitted_timestamp' => ['dbColumn' => 'uer.Timestamp', 'defaultSort' => 'desc', 'text' => 'Age'],
    'handled_timestamp'   => ['dbColumn' => 'uer.Outcome',   'defaultSort' => 'desc', 'text' => 'Checked Date'],
    'outcome'             => ['dbColumn' => 'uer.HandledTimestamp', 'defaultSort' => 'desc', 'text' => 'Outcome'],
]);
$orderBy = $heading->getOrderBy();
$dir = $heading->getOrderDir();

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($enableMan->total());

echo $Twig->render('enable/list.twig', [
    'admin'          => $_GET['handled_username'] ?? null,
    'order'          => $_GET['order'] ?? 'submitted_timestamp',
    'outcome_search' => $_GET['outcome_search'] ?? false,
    'search'         => $_GET['search'] ?? null,
    'show_checked'   => $showChecked,
    'sort'           => $_GET['sort'] ?? 'dessc',
    'username'       => $_GET['username'] ?? null,
    'view'           => $_GET['view'] ?? null,

    'heading'   => $heading,
    'outcome'   => [
        'approved'  => \Gazelle\Manager\AutoEnable::APPROVED,
        'denied'    => \Gazelle\Manager\AutoEnable::DENIED,
        'discarded' => \Gazelle\Manager\AutoEnable::DISCARDED,
    ],
    'page'      => $enableMan->page($orderBy, $dir, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'total'     => $enableMan->adminTotal(),
    'viewer'    => $Viewer,
]);
