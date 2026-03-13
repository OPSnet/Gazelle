<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . '/../forums/do_warn.php';

[$post, $body] = handleWarningRequest(new Manager\Comment());
$post->setField('Body', $body)
    ->setField('EditedUserID', $Viewer->id())->modify();
