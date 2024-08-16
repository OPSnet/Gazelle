<?php
/** @phpstan-var ?\Gazelle\User $Viewer */

if (!$Viewer || $Viewer->isLocked()) {
    require_once('webirc_disabled.php');
    exit;
}

require_once(match ($_REQUEST['action'] ?? '') {
'webirc' => 'webirc.php',
default  => 'join.php',
});
