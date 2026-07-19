<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$newTopic = randomString(13);
new User\Notification($Viewer)->setPushTopic($newTopic);
json_print('success', $newTopic);
