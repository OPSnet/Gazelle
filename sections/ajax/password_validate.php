<?php
/** @phpstan-var \Gazelle\User $Viewer */

echo (
\Gazelle\Util\PasswordCheck::checkPasswordStrength($_REQUEST['password'] ?? '', $Viewer, false) ?
    'true' : 'false'
);
