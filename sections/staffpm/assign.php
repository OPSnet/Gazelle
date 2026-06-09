<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->isStaffPMReader()) {
    Error403::error();
}

$staffPm = new Manager\StaffPM()->findById((int)($_REQUEST['convid'] ?? 0));
if (is_null($staffPm)) {
    header('Location: staffpm.php');
    exit;
}

if (isset($_GET['convid'])) {
    if ($Viewer->isFLS() && $staffPm->classLevel() > 0) {
        // FLS trying to assign non-FLS conversation
        Error403::error();
    }
    if (empty($_GET['to'])) {
        Error404::error();
    }
    $classList = new Manager\User()->classList();
    match ($_GET['to']) {
        'forum' => $staffPm->assignClass($classList[FORUM_MOD]['Level'], $Viewer),
        'staff' => $staffPm->assignClass($classList[MOD]['Level'], $Viewer),
        default => Error404::error(),
    };
    header('Location: staffpm.php');
    exit;
}

if ($Viewer->privilege()->effectiveClassLevel() < $staffPm->classLevel() && $Viewer->id !== $staffPm->assignedUserId()) {
    // Staff member is not allowed to assign conversation
    echo '-1';
} else {
    // Staff member is allowed to assign conversation
    [$assignTo, $NewLevel] = explode('_', $_POST['assign']);
    $NewLevel = (int)$NewLevel;
    if ($assignTo === 'class') {
        $staffPm->assignClass($NewLevel, $Viewer);
    } else {
        $assignee = new Manager\User()->findById($NewLevel);
        if (is_null($assignee)) {
            Error404::error();
        }
        $staffPm->assign($assignee, $Viewer);
    }
    echo '1';
}
