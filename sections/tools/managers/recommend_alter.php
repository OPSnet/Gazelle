<?php
//******************************************************************************//
//--------------- Delete a recommendation --------------------------------------//

if (!check_perms('site_recommend_own') && !check_perms('site_manage_recommendations')) {
    error(403);
}

$GroupID = $_GET['groupid'];
if (!$GroupID || !is_number($GroupID)) {
    error(404);
}

if (!check_perms('site_manage_recommendations')) {
    $DB->query("
		SELECT UserID
		FROM torrents_recommended
		WHERE GroupID = '$GroupID'");
    list($UserID) = $DB->next_record();
    if ($UserID != $LoggedUser['ID']) {
        error(403);
    }
}

$DB->query("
	DELETE FROM torrents_recommended
	WHERE GroupID = '$GroupID'");

$Cache->delete_value('recommend');
$Location = (empty($_SERVER['HTTP_REFERER'])) ? "tools.php?action=recommend" : $_SERVER['HTTP_REFERER'];
header("Location: {$Location}");
