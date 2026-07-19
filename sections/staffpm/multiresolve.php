<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$manager = new Manager\StaffPM();

$list = [];
foreach ($_POST['id'] as $id) {
    $spm = $manager->findById((int)$id);
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
