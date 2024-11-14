<?php

require_once match ($_GET['action'] ?? '') {
    'admin'          => 'admin.php',
    'browse'         => 'browse.php',
    'pair'           => 'pair.php',
    'save'           => RECOVERY ? 'save.php' : 'recover.php',
    'search', 'view' => 'view.php',
    default          => 'recover.php',
};
