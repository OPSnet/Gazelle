<?php

require_once(match ($_GET['action'] ?? '') {
    'admin' => 'admin.php',
    'edit'  => 'edit.php',
    'view'  => 'view.php',
    default => 'apply.php',
});
