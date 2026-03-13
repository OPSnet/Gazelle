<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableRequests()) {
    Error403::error('Your request privileges have been removed.');
}

require __DIR__ . match ($_REQUEST['action'] ?? '') {
    'delete', 'unfill'    => '/interim.php',
    'edit-bounty'         => '/edit_bounty.php',
    'edit'                => '/edit.php',
    'takeedit'            => '/edit_handle.php',
    'new'                 => '/new.php',
    'takenew'             => '/new_handle.php',
    'takebounty'          => '/bounty_handle.php',
    'takedelete'          => '/delete_handle.php',
    'takefill'            => '/fill_handle.php',
    'takeunfill'          => '/unfill_handle.php',
    'takevote'            => '/vote_handle.php',
    'view', 'viewrequest' => '/request.php',
    default               => '/requests.php',
};
