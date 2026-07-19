<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

echo (
Util\PasswordCheck::checkPasswordStrength($_REQUEST['password'] ?? '', $Viewer, false) ?
    'true' : 'false'
);
