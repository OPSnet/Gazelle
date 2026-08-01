<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_staffpm_stats')) {
    Error403::error();
}

$userMan = new Manager\User();
$spmMan  = new Manager\StaffPM();

$isStaffView  = ($_REQUEST['view'] ?? 'staff') === 'staff';
$SupportStaff = [...array_keys($userMan->flsList()), ...array_keys($userMan->staffList())];

echo $Twig->render('staffpm/history.twig', [
    'left' => [
        [
            'heading' => 'in the last 24 hours',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->userclassLevel(), $SupportStaff, 1),
        ],
        [
            'heading' => 'in the last week',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->userclassLevel(), $SupportStaff, 7),
        ],
        [
            'heading' => 'in the last month',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->userclassLevel(), $SupportStaff, 30),
        ],
        [
            'heading' => 'in the last quarter',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->userclassLevel(), $SupportStaff, 121),
        ],
    ],
    'right' => [
        [
            'heading' => 'all time',
            'list' => $spmMan->history($isStaffView, $userMan, $Viewer->userclassLevel(), $SupportStaff, 99999),
        ],
    ],
    'column_title' => $isStaffView ? 'Resolved' : 'PMs',
    'viewer'       => $Viewer,
]);
