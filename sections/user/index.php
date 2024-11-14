<?php
/** @phpstan-var \Gazelle\User $Viewer */

switch ($_REQUEST['action'] ?? '') {
    case '2fa':
        include_once '2fa/index.php';
        break;
    case 'audit':
        include_once 'audit.php';
        break;
    case 'dupes':
        include_once 'userlink_handle.php';
        break;
    case 'edit':
        include_once 'edit.php';
        break;
    case 'take_edit':
        include_once 'edit_handle.php';
        break;
    case 'invitetree':
        include_once 'invitetree.php';
        break;
    case 'invite':
        include_once 'invite.php';
        break;
    case 'take_invite':
        include_once 'invite_handle.php';
        break;
    case 'delete_invite':
        include_once 'delete_invite.php';
        break;
    case 'lastfm':
        include_once 'lastfm.php';
        break;
    case 'moderate':
        include_once 'moderate_handle.php';
        break;
    case 'notify':
        include_once 'notify_edit.php';
        break;
    case 'notify_handle':
        include_once 'notify_handle.php';
        break;
    case 'notify_delete':
        authorize();
        $notifId = (int)$_GET['id'];
        if ($notifId) {
            $Viewer->removeNotificationFilter($notifId);
        }
        header('Location: user.php?action=notify');
        break;
    case 'permissions':
        include_once 'permissions.php';
        break;
    case 'search':// User search
        if ($Viewer->permitted('admin_advanced_user_search') && $Viewer->permitted('users_view_ips') && $Viewer->permitted('users_view_email')) {
            include_once 'advancedsearch.php';
        } else {
            include_once 'search.php';
        }
        break;
    case 'seedbox':
        include_once 'seedbox_edit.php';
        break;
    case 'seedbox-view':
        include_once 'seedbox_view.php';
        break;
    case 'sessions':
        include_once 'sessions.php';
        break;
    case 'stats':
        include_once 'user_stats.php';
        break;
    case 'token':
        include_once 'token.php';
        break;
    case 'vote-history':
        include_once 'vote_history.php';
        break;
    case 'clearcache':
        if (!$Viewer->permittedAny('admin_clear_cache', 'users_override_paranoia')) {
            error(403);
        }
        (new Gazelle\Manager\User())->findById((int)$_REQUEST['id'])?->flush();
        include_once 'user.php';
        break;

    default:
        if (isset($_REQUEST['id'])) {
            include_once 'user.php';
        } else {
            header('Location: ' . $Viewer->location());
        }
}
