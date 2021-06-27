<?php
if (!$Viewer->isStaffPMReader()) {
    error(403);
}
if (!isset($_GET['convid']) && !isset($_POST['convid'])) {
    header('Location: staffpm.php');
    exit;
}

$Classes = (new Gazelle\Manager\User)->classList();
if ($ConvID = (int)$_GET['convid']) {
    // FLS, check level of conversation
    $Level = $DB->scalar("
        SELECT Level FROM staff_pm_conversations WHERE ID = ?
        ", $ConvID
    );

    if ($Viewer->isFLS() && $Level > 0) {
        // FLS trying to assign non-FLS conversation
        error(403);
    } else {
        // FLS conversation, assign to staff (moderator)
        if (empty($_GET['to'])) {
            error(404);
        } else {
            $Level = 0;
            switch ($_GET['to']) {
                case 'forum':
                    $Level = $Classes[FORUM_MOD]['Level'];
                    break;
                case 'staff':
                    $Level = $Classes[MOD]['Level'];
                    break;
                default:
                    error(404);
                    break;
            }

            $DB->prepared_query("
                UPDATE staff_pm_conversations SET
                    Status = 'Unanswered',
                    Level = ?
                WHERE ID = ?
                ", $Level, $ConvID
            );
            $Cache->delete_value("num_staff_pms_" . $Viewer->id());
            header('Location: staffpm.php');
        }
    }

} elseif ($ConvID = (int)$_POST['convid']) {
    // Staff (via AJAX), get current assign of conversation
    [$Level, $AssignedToUser] = $DB->row("
        SELECT Level, AssignedToUser
        FROM staff_pm_conversations
        WHERE ID = ?
        ", $ConvID
    );

    if ($Viewer->effectiveClass() < $Level && $AssignedToUser != $Viewer->id()) {
        // Staff member is not allowed to assign conversation
        echo '-1';
    } else {
        // Staff member is allowed to assign conversation
        [$LevelType, $NewLevel] = explode('_', $_POST['assign']);
        if ($LevelType == 'class') {
            // Assign to class
            $DB->prepared_query("
                UPDATE staff_pm_conversations
                SET Status = 'Unanswered',
                    Level = ?,
                    AssignedToUser = NULL
                WHERE ID = ?"
                , $NewLevel, $ConvID
            );
            $Cache->delete_value("num_staff_pms_" . $Viewer->id());
        } else {
            $assignee = new Gazelle\User($NewLevel);
            if (is_null($assignee)) {
                error(404, true);
            }
            $DB->prepared_query("
                UPDATE staff_pm_conversations SET
                    Status = 'Unanswered',
                    AssignedToUser = ?,
                    Level = ?
                WHERE ID = ?
                ", $NewLevel, $assignee->effectiveClass(), $ConvID
            );
            $Cache->delete_value("num_staff_pms_" . $Viewer->id());
        }
        echo '1';
    }
}
