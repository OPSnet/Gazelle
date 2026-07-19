<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_ipbans')) {
    Error403::error();
}

if (isset($_GET['perform'])) {
    $manager = new Manager\Ban();
    if ($_GET['perform'] == 'delete') {
        $manager->findById((int)($_GET['id']))?->remove();
    } elseif ($_GET['perform'] == 'create') {
        $manager->create($_GET['ip'], trim($_GET['notes']), $Viewer);
    } else {
        Error403::error();
    }
}
