<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_GET['type'] ?? '') {
    'posts' => '/post_history.php',
    default => json_error('bad type'),
};
