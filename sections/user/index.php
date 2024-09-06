<?php
/** @phpstan-var \Gazelle\User $Viewer */

switch ($_REQUEST['action'] ?? '') {
    case 'notify':
        require_once('notify_edit.php');
        break;
    case 'notify_handle':
        require_once('notify_handle.php');
        break;
    case 'notify_delete':
        authorize();
        $notifId = (int)$_GET['id'];
        if ($notifId) {
            $Viewer->removeNotificationFilter($notifId);
        }
        header('Location: user.php?action=notify');
        break;
    case 'search':// User search
        if ($Viewer->permitted('admin_advanced_user_search') && $Viewer->permitted('users_view_ips') && $Viewer->permitted('users_view_email')) {
            require_once('advancedsearch.php');
        } else {
            require_once('search.php');
        }
        break;
    case 'edit':
        require_once('edit.php');
        break;
    case 'take_edit':
        require_once('edit_handle.php');
        break;
    case '2fa':
        require_once('2fa/index.php');
        break;
    case 'dupes':
        require_once('userlink_handle.php');
        break;
    case 'invitetree':
        require_once('invitetree.php');
        break;
    case 'invite':
        require_once('invite.php');
        break;
    case 'take_invite':
        require_once('invite_handle.php');
        break;
    case 'delete_invite':
        require_once('delete_invite.php');
        break;
    case 'stats':
        require_once('user_stats.php');
        break;
    case 'sessions':
        require_once('sessions.php');
        break;
    case 'permissions':
        require_once('permissions.php');
        break;
    case 'moderate':
        require_once('moderate_handle.php');
        break;
    case 'seedbox':
        require_once('seedbox_edit.php');
        break;
    case 'seedbox-view':
        require_once('seedbox_view.php');
        break;
    case 'token':
        require_once('token.php');
        break;
    case 'vote-history':
        require_once('vote_history.php');
        break;
    case 'clearcache':
        if (!$Viewer->permittedAny('admin_clear_cache', 'users_override_paranoia')) {
            error(403);
        }
        (new Gazelle\Manager\User())->findById((int)$_REQUEST['id'])?->flush();
        require_once('user.php');
        break;

    case 'lastfm':
        require_once('lastfm.php');
        break;

    default:
        if (isset($_REQUEST['id'])) {
            require_once('user.php');
        } else {
            header('Location: ' . $Viewer->location());
        }
}
