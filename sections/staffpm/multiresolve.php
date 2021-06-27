<?php
$IDs = $_POST['id'];
if (!$IDs) {
    // No ID
    header("Location: staffpm.php");
    exit;
}

foreach ($IDs as $ID) {
    $ID = (int)$ID;
    // Check if conversation belongs to user
    [$UserID, $AssignedToUser, $Level] = $DB->row("
        SELECT UserID, AssignedToUser, Level
        FROM staff_pm_conversations
        WHERE ID = ?
        ", $ID
    );
    if (  (!$Viewer->isStaffPMReader() && $Viewer->id() != $UserID)
        || ($Viewer->isFLS() && !in_array($AssignedToUser, ['', $Viewer->id()]))
        || ($Viewer->isStaff() && $Level > $Viewer->effectiveClass())
    ) {
        // nope
        continue;
    }
    $DB->prepared_query("
        UPDATE staff_pm_conversations SET
            Date = now(),
            Status = 'Resolved',
            ResolverID = ?
        WHERE ID = ?
        ", $Viewer->id(), $ID
    );
}
$Cache->deleteMulti(["staff_pm_new_" . $Viewer->id(), "num_staff_pms_" . $Viewer->id()]);

header("Location: staffpm.php");
