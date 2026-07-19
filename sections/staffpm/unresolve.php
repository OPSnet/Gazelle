<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$spm = new Manager\StaffPM()->findById((int)($_GET['id'] ?? 0));
if (is_null($spm)) {
    Error404::error();
}
if (!$spm->visible($Viewer)) {
    Error403::error();
}

$spm->unresolve($Viewer);

header("Location: {$spm->location()}");
