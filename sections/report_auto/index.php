<?php

declare(strict_types=1);

namespace Gazelle;

if (isset($_REQUEST['action'])) {
    require __DIR__ . '/reports_handle.php';
} else {
    require __DIR__ . '/reports.php';
}
