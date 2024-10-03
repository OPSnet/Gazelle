<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->isStaffPMReader()) {
    error(403);
}

$name    = trim($_POST['name']);
$message = trim($_POST['message']);
if (!$name || !$message) {
    echo -1;
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$spmMan = new Gazelle\Manager\StaffPM();
$answer = $spmMan->commonAnswer($id);
if (is_null($answer)) {
    $spmMan->createCommonAnswer($name, $message);
    echo 1;
} else {
    $spmMan->modifyCommonAnswer($id, $name, $message);
    echo 2;
}
