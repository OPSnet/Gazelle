<?php
/** @phpstan-var \Gazelle\User $Viewer */

switch ($_REQUEST['action'] ?? '') {
    case 'assign':
        require('assign.php');
        break;
    case 'viewconv':
        require('viewconv.php');
        break;
    case 'takepost':
        require('viewconv_handle.php');
        break;
    case 'unresolve':
        require('unresolve.php');
        break;
    case 'multiresolve':
        require('multiresolve.php');
        break;
    case 'responses':
        require('common_responses.php');
        break;
    case 'delete_response':
        require('ajax_delete_response.php');
        break;
    case 'edit_response':
        require('ajax_edit_response.php');
        break;
    case 'get_response':
        require('ajax_get_response.php');
        break;
    case 'preview':
        echo Text::full_format($_POST['message'] ?? '');
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
        require($Viewer->isStaffPMReader() ? 'staff_inbox.php' : 'user_inbox.php');
        break;
}
