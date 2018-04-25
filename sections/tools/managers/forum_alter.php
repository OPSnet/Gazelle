<?
authorize();

if (!check_perms('admin_manage_forums')) {
	error(403);
}
$P = db_array($_POST);
$ForumManager = new \Gazelle\Manager\Forum($DB, $Cache);
if ($_POST['submit'] == 'Delete') { //Delete
	if (!is_number($_POST['id']) || $_POST['id'] == '') {
		error(0);
	}
	$ForumManager->deleteForum($_POST['id']);
} else { //Edit & Create, Shared Validation
	$Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 40 characters', array('maxlength' => 40, 'minlength' => 1));
	$Val->SetFields('description', '0', 'string', 'The description has a max length of 255 characters', array('maxlength' => 255));
	$Val->SetFields('sort', '1', 'number', 'Sort must be set');
	$Val->SetFields('categoryid', '1', 'number', 'Category must be set');
	$Val->SetFields('minclassread', '1', 'number', 'MinClassRead must be set');
	$Val->SetFields('minclasswrite', '1', 'number', 'MinClassWrite must be set');
	$Val->SetFields('minclasscreate', '1', 'number', 'MinClassCreate must be set');
	$Err = $Val->ValidateForm($_POST); // Validate the form
	if ($Err) {
		error($Err);
	}

	if ($P['minclassread'] > $LoggedUser['Class'] || $P['minclasswrite'] > $LoggedUser['Class'] || $P['minclasscreate'] > $LoggedUser['Class']) {
		error(403);
	}
	$P['autolock'] = isset($_POST['autolock']) ? '1' : '0';

	if ($_POST['submit'] == 'Edit') { //Edit
		if (!is_number($_POST['id']) || $_POST['id'] == '') {
			error(0);
		}
		$MinClassRead = $ForumManager->getMinClassRead($_POST['id']);
		if (is_null($MinClassRead)) {
			error(404);
		}
		elseif ($MinClassRead > $LoggedUser['Class']) {
			error(403);
		}

		$ForumManager->update(
            $P['id'],
			$P['sort'], $P['categoryid'], $P['name'], $P['description'], $P['minclassread'], $P['minclasswrite'], $P['minclasscreate'], $P['autolock'], $P['autolockweeks'], $P['headline'] == 'on' ? 1 : 0
		);
	} else { //Create
		$ForumManager->create(
			$P['sort'], $P['categoryid'], $P['name'], $P['description'], $P['minclassread'], $P['minclasswrite'], $P['minclasscreate'], $P['autolock'], $P['autolockweeks'], $P['headline'] == 'on' ? 1 : 0
		);
	}
}

// Go back
header('Location: tools.php?action=forum')
?>
