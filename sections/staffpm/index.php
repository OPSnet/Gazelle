<?php
enforce_login();

// Get user level
$DB->prepared_query("
    SELECT i.SupportFor,
        p.DisplayStaff
    FROM users_info AS i
    INNER JOIN users_main AS m ON (m.ID = i.UserID)
    INNER JOIN permissions AS p ON (p.ID = m.PermissionID)
    WHERE i.UserID = ?
    ", $LoggedUser['ID']
);
[$SupportFor, $DisplayStaff] = $DB->next_record();

// Logged in user is staff
$IsStaff = ($DisplayStaff == 1);

// Logged in user is Staff or FLS
$IsFLS = ($IsStaff || isset($LoggedUser['ExtraClasses'][FLS_TEAM]));

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
    case 'get_response':
        require('ajax_get_response.php');
        break;
    case 'delete_response':
        require('ajax_delete_response.php');
        break;
    case 'edit_response':
        require('ajax_edit_response.php');
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
        require($IsStaff || $IsFLS ? 'staff_inbox.php' : 'user_inbox.php');
        break;
}
