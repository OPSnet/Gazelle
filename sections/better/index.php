<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_GET['method'] ?? '') {
    'transcode' => '/transcode.php',
    default     => '/better.php',
};
