<?php

if (!$Viewer->isStaffPMReader()) {
    error(403);
}

echo (new Gazelle\Manager\StaffPM)->removeCommonAnswer((int)($_GET['id'] ?? 0));
