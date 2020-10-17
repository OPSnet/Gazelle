<?php
$ID = (int)$_GET['id'];
if (!$ID) {
    // No ID
    header("Location: staffpm.php");
    exit;
}

// Check if conversation belongs to user
[$UserID, $AssignedToUser] = $DB->row("
    SELECT UserID, AssignedToUser
    FROM staff_pm_conversations
    WHERE ID = ?
    ", $ID
);

if (!($UserID == $LoggedUser['ID'] || $IsFLS || $AssignedToUser == $LoggedUser['ID'])) {
    // Conversation does not belong to user
    error(403);
}

$DB->prepared_query("
    UPDATE staff_pm_conversations SET
        Date = now(),
        Status = 'Resolved',
        ResolverID = ?
    WHERE ID = ?
    ", $LoggedUser['ID'], $ID
);
$Cache->deleteMulti(["staff_pm_new_" . $LoggedUser['ID'], "num_staff_pms_" . $LoggedUser['ID']]);

header('Location: staffpm.php');
