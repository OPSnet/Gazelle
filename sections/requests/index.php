<?php

if ($Viewer->disableRequests()) {
    error('Your request privileges have been removed.');
}

require_once(match ($_REQUEST['action'] ?? null) {
    'delete', 'unfill'    => 'interim.php',
    'edit-bounty'         => 'edit_bounty.php',
    'new', 'edit'         => 'new_edit.php',
    'takebounty'          => 'take_bounty.php',
    'takedelete'          => 'take_delete.php',
    'takefill'            => 'take_fill.php',
    'takenew', 'takeedit' => 'take_new_edit.php',
    'takeunfill'          => 'take_unfill.php',
    'takevote'            => 'take_vote.php',
    'view', 'viewrequest' => 'request.php',
    default               => 'requests.php',
});
