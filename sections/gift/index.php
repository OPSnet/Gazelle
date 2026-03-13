<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_GET['action'] ?? '') {
    'open'  => '/open.php',
    default => '/gift.php',
};
