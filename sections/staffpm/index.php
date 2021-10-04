<?php

switch ($_REQUEST['action'] ?? '') {
    case 'viewconv':
        require('viewconv.php');
        break;
    case 'takepost':
        require('takepost.php');
        break;
    case 'resolve':
        require('resolve.php');
        break;
    case 'unresolve':
        require('unresolve.php');
        break;
    case 'multiresolve':
        require('multiresolve.php');
        break;
    case 'assign':
        require('assign.php');
        break;
    case 'responses':
        require('common_responses.php');
        break;
    case 'delete_response':
        if (!$Viewer->isStaffPMReader()) {
            error(403);
        }
        require('ajax_delete_response.php');
        break;
    case 'edit_response':
        if (!$Viewer->isStaffPMReader()) {
            error(403);
        }
        require('ajax_edit_response.php');
        break;
    case 'get_response':
        if (!$Viewer->isStaffPMReader()) {
            error(403);
        }
        require('ajax_get_response.php');
        break;
    case 'preview':
        require('ajax_preview_response.php');
        break;
    case 'get_post':
        require('get_post.php');
        break;
    case 'scoreboard':
        require('scoreboard.php');
        break;
    case 'userinbox':
        require('user_inbox.php');
        break;
    default:
        require($Viewer->isStaffPMReader()? 'staff_inbox.php' : 'user_inbox.php');
        break;
}
