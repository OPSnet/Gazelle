<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

use Enum\NotificationType;

new Manager\Notification()->push(
    [new User\Notification($Viewer)->pushToken()],
    "Notification Test",
    "Hello {$Viewer->username()}. If you can read this, you have set up your push notifications correctly.",
    ""
);
