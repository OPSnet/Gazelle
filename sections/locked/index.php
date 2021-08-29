<?php

if (!$Viewer->permitted('users_mod') && !$Viewer->isLocked()) {
    error(404);
}
require_once('default.php');
