<?
if (!($IsFLS)) {
	// Logged in user is not FLS or Staff
	error(403);
}

if ($ConvID = (int)$_GET['convid']) {
	// FLS, check level of conversation
	$DB->prepared_query("
		SELECT Level
		FROM staff_pm_conversations
		WHERE ID = ?", $ConvID);
	list($Level) = $DB->next_record();

	if ($Level == 0) {
		// FLS conversation, assign to staff (moderator)
		if (!empty($_GET['to'])) {
			$Level = 0;
			switch ($_GET['to']) {
				case 'forum':
					$Level = 650;
					break;
				case 'staff':
					$Level = 700;
					break;
				default:
					error(404);
					break;
			}

			$DB->prepared_query("
				UPDATE staff_pm_conversations
				SET Status = 'Unanswered',
					Level = ?
				WHERE ID = ?", $Level, $ConvID);
			$Cache->delete_value("num_staff_pms_$LoggedUser[ID]");
			header('Location: staffpm.php');
		} else {
			error(404);
		}
	} else {
		// FLS trying to assign non-FLS conversation
		error(403);
	}

} elseif ($ConvID = (int)$_POST['convid']) {
	// Staff (via AJAX), get current assign of conversation
	$DB->prepared_query("
		SELECT Level, AssignedToUser
		FROM staff_pm_conversations
		WHERE ID = ?", $ConvID);
	list($Level, $AssignedToUser) = $DB->next_record();
	
	$LevelCap = 1000;
	

	if ($LoggedUser['EffectiveClass'] >= min($Level, $LevelCap) || $AssignedToUser == $LoggedUser['ID']) {
		// Staff member is allowed to assign conversation, assign
		list($LevelType, $NewLevel) = explode('_', db_string($_POST['assign']));

		if ($LevelType == 'class') {
			// Assign to class
			$DB->prepared_query("
				UPDATE staff_pm_conversations
				SET Status = 'Unanswered',
					Level = ?,
					AssignedToUser = NULL
				WHERE ID = ?", $NewLevel, $ConvID);
			$Cache->delete_value("num_staff_pms_$LoggedUser[ID]");
		} else {
			$UserInfo = Users::user_info($NewLevel);
			$Level = $Classes[$UserInfo['PermissionID']]['Level'];
			if (!$Level) {
				error('Assign to user not found.');
			}

			// Assign to user
			$DB->prepared_query("
				UPDATE staff_pm_conversations
				SET Status = 'Unanswered',
					AssignedToUser = ?,
					Level = ?
				WHERE ID = ?", $NewLevel, $Level, $ConvID);
			$Cache->delete_value("num_staff_pms_$LoggedUser[ID]");
		}
		echo '1';

	} else {
		// Staff member is not allowed to assign conversation
		echo '-1';
	}

} else {
	// No ID
	header('Location: staffpm.php');
}
?>
