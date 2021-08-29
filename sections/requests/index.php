<?php

if ($Viewer->disableRequests()) {
    error('Your request privileges have been removed.');
}

$RequestTax = REQUEST_TAX;

// Minimum and default amount of upload to remove from the user when they vote.
// Also change in static/functions/requests.js
$MinimumVote = 20 * 1024 * 1024;

switch ($_REQUEST['action'] ?? null) {
    case 'new':
    case 'edit':
        require('new_edit.php');
        break;
    case 'edit-bounty':
        require('edit_bounty.php');
        break;
    case 'takebounty':
        require('take_bounty.php');
        break;
    case 'takevote':
        require('take_vote.php');
        break;
    case 'takefill':
        require('take_fill.php');
        break;
    case 'takenew':
    case 'takeedit':
        require('take_new_edit.php');
        break;
    case 'delete':
    case 'unfill':
        require('interim.php');
        break;
    case 'takeunfill':
        require('take_unfill.php');
        break;
    case 'takedelete':
        require('take_delete.php');
        break;
    case 'view':
    case 'viewrequest':
        require('request.php');
        break;
    default:
        require('requests.php');
        break;
}
