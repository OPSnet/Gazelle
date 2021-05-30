<?php

if (isset($_POST['quickpost'])) {
    if (isset($_POST['subject'])) {
        // New staff PM conversation
        if (!isset($_POST['level'])) {
            error("Unclear on the recipient");
        }
        $subject = trim($_POST['subject'] ?? '');
        if (empty($subject)) {
            error("You must provide a subject for your message");
        }
        $message = trim($_POST['quickpost'] ?? '');
        if (empty($message)) {
            error("You must write something in your message");
        }
        $DB->begin_transaction();
        $DB->prepared_query("
            INSERT INTO staff_pm_conversations
                   (Subject, Level, UserID, Status,       Date)
            VALUES (?,       ?,     ?,      'Unanswered', now())
            ", $subject, (int)$_POST['level'], $LoggedUser['ID']
        );
        $ConvID = $DB->inserted_id();
        $DB->prepared_query("
            INSERT INTO staff_pm_messages
                   (UserID, Message, ConvID, SentDate)
            VALUES (?,      ?,       ?,      now())
            ", $LoggedUser['ID'], $message, $ConvID
        );
        $DB->commit();
        header('Location: staffpm.php');
    } else {
        $ConvID = (int)$_POST['convid'];
        if (!$ConvID) {
            header("Location: staffpm.php");
        } else {
            // Check if conversation belongs to user
            [$UserID, $AssignedToUser, $Level] = $DB->row("
                SELECT UserID, AssignedToUser, Level
                FROM staff_pm_conversations
                WHERE ID = ?
                ", $ConvID
            );

            if (  (!$user->isStaffPMReader() && (!in_array($LoggedUser['ID'], [$UserID, $AssignedToUser])))
                || ($user->isFLS() && !in_array($AssignedToUser, ['', $LoggedUser['ID']]))
                || ($user->isStaff() && $Level > $user->effectiveClass())
            ) {
                // User is trying to respond to conversation that does no belong to them
                error(403);
            } else {
                // Response to existing conversation
                $message = trim($_POST['quickpost'] ?? '');
                $DB->begin_transaction();
                $DB->prepared_query("
                    INSERT INTO staff_pm_messages
                           (UserID, Message, ConvID, SentDate)
                    VALUES (?,      ?,       ?,      now())
                    ", $LoggedUser['ID'], $message, $ConvID
                );
                $DB->prepared_query("
                    UPDATE staff_pm_conversations SET
                        Date = now(),
                        Unread = true,
                        Status = ?
                    WHERE ID = ?
                    ", $user->isStaffPMReader() ? 'Open' : 'Unanswered', $ConvID
                );
                $DB->commit();
                $Cache->deleteMulti([ "staff_pm_new_{$UserID}", "num_staff_pms_" . $LoggedUser['ID'], "staff_pm_new_" . $LoggedUser['ID']]);

                header("Location: staffpm.php?action=viewconv&id=$ConvID");
            }
        }
    }
} elseif (isset($_POST['convid'])) {
    header("Location: staffpm.php?action=viewconv&id=" . (int)$_POST['convid']);
} else {
    error('You have not entered a message for your StaffPM. Please go back and do so.');
}
