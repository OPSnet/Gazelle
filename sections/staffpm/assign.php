<?php

if (!$Viewer->isStaffPMReader()) {
    error(403);
}

$staffPm = (new Gazelle\Manager\StaffPM)->findById((int)($_REQUEST['convid'] ?? 0));
if (is_null($staffPm)) {
    header('Location: staffpm.php');
    exit;
}

if (isset($_GET['convid'])) {
    if ($Viewer->isFLS() && $staffPm->classLevel() > 0) {
        // FLS trying to assign non-FLS conversation
        error(403);
    }
    if (empty($_GET['to'])) {
        error(404);
    }
    $classList = (new Gazelle\Manager\User)->classList();
    switch ($_GET['to']) {
        case 'forum':
            $staffPm->assignClass($classList[FORUM_MOD]['Level'], $Viewer);
            break;
        case 'staff':
            $staffPm->assignClass($classList[MOD]['Level'], $Viewer);
            break;
        default:
            error(404);
            break;
    }
    header('Location: staffpm.php');
    exit;
}

if ($Viewer->effectiveClass() < $staffPm->classLevel() && $Viewer->id() != $staffPm->assignedUserId()) {
    // Staff member is not allowed to assign conversation
    echo '-1';
} else {
    // Staff member is allowed to assign conversation
    [$assignTo, $NewLevel] = explode('_', $_POST['assign']);
    if ($assignTo == 'class') {
        $staffPm->assignClass($NewLevel, $Viewer);
    } else {
        $assignee = new Gazelle\User($NewLevel);
        if (is_null($assignee)) {
            error(404, true);
        }
        $staffPm->assign($assignee, $Viewer);
    }
    echo '1';
}
