<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
$Viewer->logout();
header("Location: /index.php");
