<?php
if (!check_perms('admin_reports')) {
    error(403);
}

$id = (int)$_GET['id'];
if ($id) {
    echo (new Gazelle\ReportV2($id))->claim($Viewer->id());
}
