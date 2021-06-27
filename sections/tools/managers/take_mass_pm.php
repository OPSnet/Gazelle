<?php
set_time_limit(0);

authorize();

if (!check_perms("admin_global_notification")) {
    error(403);
}

if (!is_number($_POST['class_id']) || empty($_POST['subject']) || empty($_POST['body'])) {
    error("Error in message form");
}

$permissionId = $_POST['class_id'];
$fromId = empty($_POST['from_system']) ? $Viewer->id() : 0;
$DB->prepared_query("
    (SELECT ID AS UserID FROM users_main WHERE PermissionID = ? AND ID != ?)
    UNION DISTINCT
    (SELECT UserID FROM users_levels WHERE PermissionID = ? AND UserID != ?)
    ", $permissionId, $fromId, $permissionId, $fromId
);

$userMan = new Gazelle\Manager\User;
$subject = trim($_POST['subject']);
$body = trim($_POST['body']);
while([$userId] = $DB->next_record()) {
   $userMan->sendPM($userId, $fromId, $subject, $body);
}

header("Location: tools.php");
