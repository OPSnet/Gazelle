<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->isStaffPMReader()) {
    Error403::error();
}

$answer = new Manager\StaffPM()->commonAnswer((int)($_GET['id'] ?? 0));
echo (int)($_GET['plain'] ?? 0) === 1 ? $answer : \Text::full_format($answer);
