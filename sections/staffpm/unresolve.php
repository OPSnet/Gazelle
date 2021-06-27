<?php
$ID = (int)$_GET['id'];
if (!$ID) {
    header('Location: staffpm.php');
    exit;
}

// Check if conversation belongs to user
[$UserID, $AssignedToUser, $Level] = $DB->row("
    SELECT UserID, AssignedToUser, Level
    FROM staff_pm_conversations
    WHERE ID = ?
    ", $ID
);

if (  (!$Viewer->isStaffPMReader() && !in_array($Viewer->id(), [$UserID, $AssignedToUser]))
    || ($Viewer->isFLS() && !in_array($AssignedToUser, ['', $Viewer->id()]))
    || ($Viewer->isStaff() && $Level > $Viewer->effectiveClass())
) {
    error(403);
}

// Conversation belongs to user or user is staff, unresolve it
$DB->prepared_query("
    UPDATE staff_pm_conversations SET
        Date = now(),
        Status = 'Unanswered'
    WHERE ID = ?
    ", $ID
);
$Cache->delete_value("num_staff_pms_" . $Viewer->id());

header('Location: staffpm.php');
