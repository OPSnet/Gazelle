<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$staffpmMan = new Gazelle\Manager\StaffPM();
$viewMap = [
    '' => [
        'status' => ['Unanswered'],
        'count'  => $Viewer->isFLS()
            ? $staffpmMan->countByStatus($Viewer, ['Unanswered'])
            : $staffpmMan->countAtLevel($Viewer, ['Unanswered']),
        'title'  => 'Your Unanswered',
    ],
    'open' => [
        'status' => ['Open'],
        'count'  => $staffpmMan->countByStatus($Viewer, ['Open']),
        'title'  => 'Waiting for reply',
        'view'   => 'Unresolved',
    ],
    'resolved' => [
        'status' => ['Resolved'],
        'title'  => 'Resolved',
        'view'   => 'Resolved',
    ],
];
if ($Viewer->isStaff()) {
    $viewMap = array_merge([
        'unanswered' => [
            'count'  => $staffpmMan->countByStatus($Viewer, ['Unanswered']),
            'status' => ['Unanswered'],
            'title'  => 'All unanswered',
            'view'   => 'Unanswered',
        ]],
        $viewMap
    );
}

$view = $_GET['view'] ?? '';
if (!isset($viewMap[$view])) {
    error('Unknown staff inbox view parameter');
}

if (isset($_GET['id'])) {
    $staffpmMan->setSearchId($Viewer, (int)$_GET['id']);
} else {
    $staffpmMan->setSearchStatusList($Viewer, $viewMap[$view]['status']);
}

if ($viewMap[$view]['title'] === 'Your Unanswered') {
    $classlist = (new Gazelle\Manager\User())->classList();
    if ($Viewer->privilege()->effectiveClassLevel() >= $classlist[MOD]['Level']) {
        $staffpmMan->setUserclassLevel($classlist[MOD]['Level']);
    } elseif ($Viewer->privilege()->effectiveClassLevel() == $classlist[FORUM_MOD]['Level']) {
        $staffpmMan->setUserclassLevel($classlist[FORUM_MOD]['Level']);
    }
}

$paginator = new Gazelle\Util\Paginator(MESSAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($staffpmMan->searchTotal());

echo $Twig->render('staffpm/staff-inbox.twig', [
    'page'       => $staffpmMan->page($Viewer, $paginator->limit(), $paginator->offset()),
    'paginator'  => $paginator,
    'view_map'   => $viewMap,
    'view'       => $view,
    'viewer'     => $Viewer,
]);
