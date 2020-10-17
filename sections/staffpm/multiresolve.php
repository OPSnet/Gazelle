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
        WHERE ID = $ID");

    if ($UserID == $LoggedUser['ID'] || $DisplayStaff == '1' || $UserID == $AssignedToUser) {
        // Trying to run disallowed query, stop here
        break;
    } else {
        // Conversation belongs to user or user is staff
        $DB->prepared_query("
            UPDATE staff_pm_conversations SET
                Date = now(),
                Status = 'Resolved',
                ResolverID = ?
            WHERE ID = ?
            ", $LoggedUser['ID'], $ID
        );
    }
}
$Cache->deleteMulti(["staff_pm_new_" . $LoggedUser['ID'], "num_staff_pms_" . $LoggedUser['ID']]);

header("Location: staffpm.php");
