<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->isStaffPMReader()) {
    Error403::error();
}

authorize();

echo new Manager\StaffPM()->removeCommonAnswer((int)($_POST['id'] ?? 0));
