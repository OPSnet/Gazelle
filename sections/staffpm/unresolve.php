<?php
$ID = (int)$_GET['id'];
if (!$ID) {
    header('Location: staffpm.php');
    exit;
}

// Check if conversation belongs to user
[$UserID, $Level, $AssignedToUser] = $DB->row("
    SELECT UserID, Level, AssignedToUser
    FROM staff_pm_conversations
    WHERE ID = ?
    ", $ID
);

if (!($UserID == $LoggedUser['ID']
    || ($IsFLS && $Level == 0)
    || $AssignedToUser == $LoggedUser['ID']
    || ($IsStaff && $Level <= $LoggedUser['EffectiveClass'])
)) {
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
$Cache->delete_value("num_staff_pms_" . $LoggedUser['ID']);

header('Location: staffpm.php');
