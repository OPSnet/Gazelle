<?php

if ($Viewer->disableRequests()) {
    error('Your request privileges have been removed.');
}

require_once(match ($_REQUEST['action'] ?? null) {
    'delete', 'unfill'    => 'interim.php',
    'edit-bounty'         => 'edit_bounty.php',
    'new', 'edit'         => 'new_edit.php',
    'takebounty'          => 'bounty_handle.php',
    'takedelete'          => 'delete_handle.php',
    'takefill'            => 'take_fill.php',
    'takenew', 'takeedit' => 'take_new_edit.php',
    'takeunfill'          => 'unfill_handle.php',
    'takevote'            => 'vote_handle.php',
    'view', 'viewrequest' => 'request.php',
    default               => 'requests.php',
});
