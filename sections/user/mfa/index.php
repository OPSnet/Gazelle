<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$user = new Manager\User()->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    Error404::error('No such user');
}
if ($user->id != $Viewer->id && !$Viewer->permitted('users_mod')) {
    Error403::error();
}

require __DIR__ . match ($_GET['do'] ?? '') {
    'configure' => '/configure.php',
    'remove'    => '/remove.php',
    default     => Error404::error(),
};
