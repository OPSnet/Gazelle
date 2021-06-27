<?php
enforce_login();

switch ($_GET['action'] ?? '') {
    case 'admin':
        if (check_perms('admin_manage_applicants')) {
            require_once('admin.php');
        } else {
            require_once('apply.php');
        }
        break;

    case 'view':
        $appMan = new Gazelle\Manager\Applicant;
        if (check_perms('admin_manage_applicants') || (!check_perms('admin_manage_applicants') && $appMan->userIsApplicant($Viewer->id()))) {
            require_once('view.php');
        } else {
            require_once('apply.php');
        }
        break;

    case 'save':
    default:
        require_once('apply.php');
        break;
}
