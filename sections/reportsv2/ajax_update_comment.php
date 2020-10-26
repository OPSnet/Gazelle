<?php
// perform the back end of updating a report comment

if (!check_perms('admin_reports')) {
    error(403);
}

authorize();
$id = (int)$_GET['id'];
if ($id) {
    (new Gazelle\ReportV2($id))->comment($_POST['comment']);
}
