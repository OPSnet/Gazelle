<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->isStaffPMReader()) {
    Error403::error();
}

$name    = trim($_POST['name']);
$message = trim($_POST['message']);
if (!$name || !$message) {
    echo -1;
    return;
}

$id     = (int)($_POST['id'] ?? 0);
$spmMan = new Manager\StaffPM();
$answer = $spmMan->commonAnswer($id);
if (is_null($answer)) {
    $spmMan->createCommonAnswer($name, $message);
    echo 1;
} else {
    $spmMan->modifyCommonAnswer($id, $name, $message);
    echo 2;
}
