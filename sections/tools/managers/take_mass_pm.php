<?php

if (!$Viewer->permitted("admin_global_notification")) {
    error(403);
}

authorize();

if (!is_number($_POST['class_id']) || empty($_POST['subject']) || empty($_POST['body'])) {
    error("Error in message form");
}

set_time_limit(0);

$permissionId = $_POST['class_id'];
$fromId = empty($_POST['from_system']) ? $Viewer->id() : 0;
$db = Gazelle\DB::DB();
$db->prepared_query("
    (SELECT ID AS UserID FROM users_main WHERE PermissionID = ? AND ID != ?)
    UNION DISTINCT
    (SELECT UserID FROM users_levels WHERE PermissionID = ? AND UserID != ?)
    ", $permissionId, $fromId, $permissionId, $fromId
);

$userMan = new Gazelle\Manager\User;
$subject = trim($_POST['subject']);
$body = trim($_POST['body']);
while([$userId] = $db->next_record()) {
   $userMan->sendPM($userId, $fromId, $subject, $body);
}

header("Location: tools.php");
