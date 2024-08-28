<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->isStaffPMReader()) {
    error(403);
}

authorize();

echo (new Gazelle\Manager\StaffPM())->removeCommonAnswer((int)($_POST['id'] ?? 0));
