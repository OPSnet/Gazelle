<?php

declare(strict_types=1);

namespace Gazelle;

require_once __DIR__ . '/' . match ($_GET['type'] ?? null) {
    'posts' => 'post_history.php',
    default => json_error('bad type'),
};
