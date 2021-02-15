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
    [$UserID, $AssignedToUser] = $DB->row("
        SELECT UserID, AssignedToUser
        FROM staff_pm_conversations
        WHERE ID = ?
        ", $ID
    );
    if (  (!$user->isStaffPMReader() && $LoggedUser['ID'] != $UserID)
        || ($user->isFLS() && !in_array($AssignedToUser, ['', $LoggedUser['ID']]))
        || ($user->isStaff() && $Level > $user->effectiveClass())
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
        ", $LoggedUser['ID'], $ID
    );
}
$Cache->deleteMulti(["staff_pm_new_" . $LoggedUser['ID'], "num_staff_pms_" . $LoggedUser['ID']]);

header("Location: staffpm.php");
