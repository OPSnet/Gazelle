<?php

if ($Viewer->disableRequests()) {
    error('Your request privileges have been removed.');
}

switch ($_REQUEST['action'] ?? null) {
    case 'new':
    case 'edit':
        require_once('new_edit.php');
        break;
    case 'edit-bounty':
        require_once('edit_bounty.php');
        break;
    case 'takebounty':
        require_once('take_bounty.php');
        break;
    case 'takevote':
        require_once('take_vote.php');
        break;
    case 'takefill':
        require_once('take_fill.php');
        break;
    case 'takenew':
    case 'takeedit':
        require_once('take_new_edit.php');
        break;
    case 'delete':
    case 'unfill':
        require_once('interim.php');
        break;
    case 'takeunfill':
        require_once('take_unfill.php');
        break;
    case 'takedelete':
        require_once('take_delete.php');
        break;
    case 'view':
    case 'viewrequest':
        require_once('request.php');
        break;
    default:
        require_once('requests.php');
        break;
}
