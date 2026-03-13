<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

switch ($_REQUEST['action'] ?? '') {
    case 'audit':
        require __DIR__ . '/audit.php';
        break;
    case 'audit-edit':
        require __DIR__ . '/audit-edit.php';
        break;
    case 'audit-edit-handle':
        require __DIR__ . '/audit-edit-handle.php';
        break;
    case 'dupes':
        require __DIR__ . '/userlink_handle.php';
        break;
    case 'edit':
        require __DIR__ . '/edit.php';
        break;
    case 'take_edit':
        require __DIR__ . '/edit_handle.php';
        break;
    case 'invitetree':
        require __DIR__ . '/invitetree.php';
        break;
    case 'invite':
        require __DIR__ . '/invite.php';
        break;
    case 'take_invite':
        require __DIR__ . '/invite_handle.php';
        break;
    case 'delete_invite':
        require __DIR__ . '/delete_invite.php';
        break;
    case 'load-invitetree':
        require __DIR__ . '/load-invitetree.php';
        break;
    case 'lastfm':
        require __DIR__ . '/lastfm.php';
        break;
    case 'mfa':
        require __DIR__ . '/mfa/index.php';
        break;
    case 'moderate':
        require __DIR__ . '/moderate_handle.php';
        break;
    case 'notify':
        require __DIR__ . '/notify_edit.php';
        break;
    case 'notify_handle':
        require __DIR__ . '/notify_handle.php';
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
        require __DIR__ . '/permissions.php';
        break;
    case 'search':// User search
        require $Viewer->permitted('admin_advanced_user_search')
                && $Viewer->permitted('users_view_ips')
                && $Viewer->permitted('users_view_email')
            ? 'advancedsearch.php'
            : 'search.php';
        break;
    case 'seedbox':
        require __DIR__ . '/seedbox_edit.php';
        break;
    case 'seedbox-view':
        require __DIR__ . '/seedbox_view.php';
        break;
    case 'sessions':
        require __DIR__ . '/sessions.php';
        break;
    case 'stats':
        require __DIR__ . '/user_stats.php';
        break;
    case 'token':
        require __DIR__ . '/token.php';
        break;
    case 'vote-history':
        require __DIR__ . '/vote_history.php';
        break;
    case 'clearcache':
        if (!$Viewer->permittedAny('admin_clear_cache', 'users_override_paranoia')) {
            Error403::error();
        }
        new Manager\User()->findById((int)$_REQUEST['id'])?->flush();
        require __DIR__ . '/user.php';
        break;

    default:
        if (isset($_REQUEST['id'])) {
            require __DIR__ . '/user.php';
        } else {
            header('Location: ' . $Viewer->location());
        }
}
