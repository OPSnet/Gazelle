<?php
/** @phpstan-var \Gazelle\User $Viewer */

$spm = (new Gazelle\Manager\StaffPM())->findById((int)($_GET['id'] ?? 0));
if (is_null($spm)) {
    error(404);
}
if (!$spm->visible($Viewer)) {
    error(403);
}

$spm->unresolve($Viewer);

header("Location: {$spm->location()}");
