<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_GET['type'] ?? '') {
    'viewconv' => '/viewconv.php',
    default    => '/inbox.php',
};
