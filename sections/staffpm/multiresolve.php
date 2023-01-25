<?php

$manager = new Gazelle\Manager\StaffPM;

$list = [];
foreach ($_POST['id'] as $id) {
    $spm = $manager->findById($id);
    if ($spm) {
        $list[] = $spm;
    }
}
if (!$list) {
    header("Location: staffpm.php");
    exit;
}

foreach ($list as $spm) {
    if ($spm->visible($Viewer)) {
        $spm->resolve($Viewer);
    }
}

header("Location: staffpm.php");
