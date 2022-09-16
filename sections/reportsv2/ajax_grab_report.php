<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    echo (new Gazelle\ReportV2($id))->claim($Viewer->id());
}
