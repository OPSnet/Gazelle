<?php

declare(strict_types=1);

namespace Gazelle;

require_once __DIR__ . '/' . match ($_GET['method'] ?? null) {
    'transcode' => 'transcode.php',
    'single'    => 'single.php',
    default     => json_error('bad method'),
};
