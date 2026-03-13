<?php
/** @phpstan-var ?\Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer || $Viewer->isLocked()) {
    require __DIR__ . '/webirc_disabled.php';
    exit;
}

require match ($_REQUEST['action'] ?? '') {
    'webirc' => __DIR__ . '/webirc.php',
    default  => __DIR__ . '/join.php',
};
