<?php
/** @phpstan-var \Gazelle\User $Viewer */

$spm = (new Gazelle\Manager\StaffPM())->findById((int)($_GET['id'] ?? 0));
if (is_null($spm)) {
    header('Location: staffpm.php');
    exit;
}
if (!$spm->visible($Viewer)) {
    error(403);
}

$spm->unresolve($Viewer);

header('Location: staffpm.php');
