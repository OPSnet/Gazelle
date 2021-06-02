<?php
//TODO
/*****************************************************************
Finish removing the take[action] pages and utilize the index correctly
Should the advanced search really only show if they match 3 perms?
Make sure all constants are defined in config.php and not in random files
*****************************************************************/

enforce_login();

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
            $user = new Gazelle\User($LoggedUser['ID']);
            $user->removeNotificationFilter($notifId);
        }
        header('Location: user.php?action=notify');
        break;
    case 'search':// User search
        if (check_perms('admin_advanced_user_search') && check_perms('users_view_ips') && check_perms('users_view_email')) {
            require_once('advancedsearch.php');
        }
        else {
            require_once('search.php');
        }
        break;
    case 'edit':
        if (isset($_REQUEST['userid'])) {
            require_once('edit.php');
        }
        else {
            header("Location: user.php?action=edit&userid={$LoggedUser['ID']}");
        }
        break;
    case '2fa':
        require_once('2fa/index.php');
        break;
    case 'take_edit':
        require_once('take_edit.php');
        break;
    case 'dupes':
        require_once('manage_linked.php');
        break;
    case 'invitetree':
        require_once('invitetree.php');
        break;
    case 'invite':
        require_once('invite.php');
        break;
    case 'take_invite':
        require_once('take_invite.php');
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
    case 'similar':
        require_once('similar.php');
        break;
    case 'moderate':
        require_once('takemoderate.php');
        break;
    case 'seedbox':
        require_once('seedbox_edit.php');
        break;
    case 'seedbox-view':
        require_once('seedbox_view.php');
        break;
    case 'token':
        require_once(__DIR__ . '/token.php');
        break;
    case 'clearcache':
        if (!check_perms('admin_clear_cache') || !check_perms('users_override_paranoia')) {
            error(403);
        }
        $UserID = $_REQUEST['id'];
        $Cache->deleteMulti([
            'collage_subs_user_new_'  . $UserID,
            'donor_info_'             . $UserID,
            'inbox_new_'              . $UserID,
            'user_notify_upload_'     . $UserID,
            'staff_pm_new_'           . $UserID,
            'subscriptions_user_new_' . $UserID,
            'user_info_'              . $UserID,
            'user_info_heavy_'        . $UserID,
        ]);
        require_once('user.php');
        break;

    // Provide public methods for Last.fm data gets.
    case 'lastfm_compare':
        if (isset($_GET['username'])) {
            echo (new Gazelle\Util\LastFM)->compare($LoggedUser['ID'], $_GET['username']);
        }
        break;
    case 'lastfm_last_played_track':
        if (isset($_GET['username'])) {
            echo (new Gazelle\Util\LastFM)->lastTrack($_GET['username']);
        }
        break;
    case 'lastfm_top_artists':
        if (isset($_GET['username'])) {
            echo (new Gazelle\Util\LastFM)->topArtists($_GET['username']);
        }
        break;
    case 'lastfm_top_albums':
        if (isset($_GET['username'])) {
            echo (new Gazelle\Util\LastFM)->topAlbums($_GET['username']);
        }
        break;
    case 'lastfm_top_tracks':
        if (isset($_GET['username'])) {
            echo (new Gazelle\Util\LastFM)->topTracks($_GET['username']);
        }
        break;
    case 'lastfm_clear_cache':
        if (isset($_GET['username']) && isset($_GET['uid'])) {
            echo (new Gazelle\Util\LastFM)->clear($LoggedUser['ID'], $_GET['username'],$_GET['uid']);
        }
        break;
    default:
        if (isset($_REQUEST['id'])) {
            require_once('user.php');
        } else {
            header("Location: user.php?id={$LoggedUser['ID']}");
        }
}
