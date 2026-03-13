<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_GET['action'] ?? '') {
    'admin' => '/admin.php',
    'edit'  => '/edit.php',
    'view'  => '/view.php',
    default => '/apply.php',
};
