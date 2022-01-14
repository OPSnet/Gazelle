<?php

$spm = (new Gazelle\Manager\StaffPM)->findById((int)($_GET['id'] ?? 0));
if (is_null($spm)) {
    header('Location: staffpm.php');
    exit;
}
if ($spm->isReadable($Viewer)) {
    error(403);
}

$spm->unresolve();
$Cache->delete_value("num_staff_pms_" . $Viewer->id());

header('Location: staffpm.php');
