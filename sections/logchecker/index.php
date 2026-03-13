<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_REQUEST['action'] ?? '') {
    'update'      => '/update.php',
    'upload'      => '/upload.php',
    'take_upload' => '/upload_handle.php',
    'take_test'   => '/test_handle.php',
    default       => '/test.php',
};
