<?php

if (!$Viewer->permitted('admin_staffpm_stats')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$spmMan  = new Gazelle\Manager\StaffPM;

$isStaffView  = ($_REQUEST['view'] ?? 'staff') === 'staff';
$SupportStaff = array_merge(array_keys($userMan->flsList()), array_keys($userMan->staffList()));

echo $Twig->render('staffpm/history.twig', [
    'left' => [
        [
            'heading' => 'in the last 24 hours',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->classLevel(), $SupportStaff, 1),
        ],
        [
            'heading' => 'in the last week',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->classLevel(), $SupportStaff, 7),
        ],
        [
            'heading' => 'in the last month',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->classLevel(), $SupportStaff, 30),
        ],
        [
            'heading' => 'in the last quarter',
            'list'    => $spmMan->history($isStaffView, $userMan, $Viewer->classLevel(), $SupportStaff, 121),
        ],
    ],
    'right' => [
        [
            'heading' => 'all time',
            'list' => $spmMan->history($isStaffView, $userMan, $Viewer->classLevel(), $SupportStaff, 99999),
        ],
    ],
    'column_title' => $isStaffView ? 'Resolved' : 'PMs',
    'viewer'       => $Viewer,
]);
