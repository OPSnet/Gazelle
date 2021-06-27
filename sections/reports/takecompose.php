<?php
authorize();


if (empty($_POST['toid'])) {
    error(404);
}

if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$_POST['toid']])) {
    error(403);
}

$ConvID = false;
if (isset($_POST['convid']) && is_number($_POST['convid'])) {
    $ConvID = $_POST['convid'];
    $Subject = '';
    $ToID = explode(',', $_POST['toid']);
    foreach ($ToID as $TID) {
        if (!is_number($TID)) {
            $Err = 'A recipient does not exist.';
        }
    }
    $DB->prepared_query("
        SELECT UserID
        FROM pm_conversations_users
        WHERE UserID = ?
            AND ConvID = ?
        ", $Viewer->id(), $ConvID
    );
    if (!$DB->has_results()) {
        error(403);
    }
} else {
    if (!is_number($_POST['toid'])) {
        $Err = 'This recipient does not exist.';
    } else {
        $ToID = $_POST['toid'];
    }
    $Subject = trim($_POST['subject']);
    if (empty($Subject)) {
        $Err = "You can't send a message without a subject.";
    }
}
$Body = trim($_POST['body']);
if ($Body === '' || $Body === false) {
    $Err = "You can't send a message without a body!";
}

if (!empty($Err)) {
    error($Err);
    $ToID = $_POST['toid'];
    $Return = true;
    require_once(__DIR__ . '/../inbox/compose.php');
    die();
}

if ($ConvID) {
    (new Gazelle\Manager\User)->replyPM($ToID, $Viewer->id(), $Subject, $Body, $ConvID);
} else {
    (new Gazelle\Manager\User)->sendPM($ToID, $Viewer->id(), $Subject, $Body);
}

header('Location: reports.php');
