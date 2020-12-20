<?php
// perform the back end of updating a report comment

authorize();

if (!check_perms('admin_reports')) {
    error(403);
}

$id = (int)$_POST['reportid'];
if ($id) {
    (new Gazelle\ReportV2($id))->comment($_POST['comment']);
}
