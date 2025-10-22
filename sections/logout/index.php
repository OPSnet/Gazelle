<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
$Viewer->session()->logout();
header("Location: /index.php");
