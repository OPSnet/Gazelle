<?php
/** @phpstan-var ?\Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!isset($Viewer)) {
    require __DIR__ . '/public.php';
} else {
    if (!isset($_REQUEST['action'])) {
        require __DIR__ . '/private.php';
    } else {
        match ($_REQUEST['action']) {
            'poll'  => require __DIR__ . '/../forums/poll_vote.php',
            default => Error400::error('Unknown action requested'),
        };
    }
}
