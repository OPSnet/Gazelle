<?php
/** @phpstan-var \Gazelle\User $Viewer */

switch ($_REQUEST['action'] ?? '') {
    case 'assign':
        include 'assign.php';
        break;
    case 'viewconv':
        include 'viewconv.php';
        break;
    case 'takepost':
        include 'viewconv_handle.php';
        break;
    case 'unresolve':
        include 'unresolve.php';
        break;
    case 'multiresolve':
        include 'multiresolve.php';
        break;
    case 'responses':
        include 'common_responses.php';
        break;
    case 'delete_response':
        include 'ajax_delete_response.php';
        break;
    case 'edit_response':
        include 'ajax_edit_response.php';
        break;
    case 'get_response':
        include 'ajax_get_response.php';
        break;
    case 'preview':
        echo Text::full_format($_POST['message'] ?? '');
        break;
    case 'get_post':
        include 'get_post.php';
        break;
    case 'scoreboard':
        include 'scoreboard.php';
        break;
    case 'userinbox':
        include 'user_inbox.php';
        break;
    default:
        include $Viewer->isStaffPMReader() ? 'staff_inbox.php' : 'user_inbox.php';
        break;
}
