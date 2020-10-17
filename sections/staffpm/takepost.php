<?php
$Message = trim($_POST['message']);
$Subject = trim($_POST['subject']);
$ConvID = (int)$_POST['convid'];

if ($Message) {
    if ($Subject) {
        // New staff PM conversation
        assert_numbers($_POST, ['level'], 'Invalid recipient');
        $DB->prepared_query("
            INSERT INTO staff_pm_conversations
                   (Subject, Level, UserID, Status,       Date)
            VALUES (?,       ?,     ?,      'Unanswered', now())
            ", $Subject, $_POST['level'], $LoggedUser['ID']
        );
        $ConvID = $DB->inserted_id();
        $DB->prepared_query("
            INSERT INTO staff_pm_messages
                   (UserID, Message, ConvID, SentDate)
            VALUES (?,      ?,       ?,      now())
            ", $LoggedUser['ID'], $Message, $ConvID
        );
        header('Location: staffpm.php');

    } elseif ($ConvID) {
        // Check if conversation belongs to user
        [$UserID, $AssignedToUser, $Level] = $DB->row("
            SELECT UserID, AssignedToUser, Level
            FROM staff_pm_conversations
            WHERE ID = ?
            ", $ConvID
        );

        $Level = min($Level, 1000);
        if (!($UserID == $LoggedUser['ID'] || ($IsFLS && $LoggedUser['EffectiveClass'] >= $Level) || $UserID == $AssignedToUser)) {
            // User is trying to respond to conversation that does no belong to them
            error(403);
        } else {
            // Response to existing conversation
            $DB->prepared_query("
                INSERT INTO staff_pm_messages
                       (UserID, Message, ConvID, SentDate)
                VALUES (?,      ?,       ?,      now())
                ", $LoggedUser['ID'], $Message, $ConvID
            );

            // Update conversation
            $DB->prepared_query("
                UPDATE staff_pm_conversations SET
                    Date = now(),
                    Unread = true,
                    Status = ?
                WHERE ID = ?
                ", $IsFLS ? 'Open' : 'Unanswered', $ConvID
            );
            $Cache->deleteMulti([ "staff_pm_new_{$UserID}", "num_staff_pms_" . $LoggedUser['ID'], "staff_pm_new_" . $LoggedUser['ID']]);

            header("Location: staffpm.php?action=viewconv&id=$ConvID");
        }
    } else {
        // Message but no subject or conversation ID
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    }
} elseif ($ConvID = (int)$_POST['convid']) {
    // No message, but conversation ID
    header("Location: staffpm.php?action=viewconv&id=$ConvID");
} else {
    // No message or conversation ID
    error('You have not entered a message for your StaffPM. Please go back and do so.');
}
