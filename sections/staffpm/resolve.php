<?php

$staffPM = (new Gazelle\Manager\StaffPM())->findById((int)($_GET['id'] ?? 0));
if (is_null($staffPM)) {
    header("Location: staffpm.php");
    exit;
}

if (!$staffPM->visible($Viewer)) {
    error(403);
}
$staffPM->resolve($Viewer);

header('Location: staffpm.php');
