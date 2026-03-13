<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_REQUEST['action'] ?? '') {
    'disabled'    => '/disabled.php',
    'recover'     => isset($_REQUEST['key']) ? '/recover_step2.php' : '/recover_step1.php',
    default       => '/login.php',
};
