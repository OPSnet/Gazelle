<?php
//TODO
/*****************************************************************
Finish removing the take[action] pages and utilize the index correctly
Should the advanced search really only show if they match 3 perms?
Make sure all constants are defined in config.php and not in random files
*****************************************************************/
enforce_login();
include(SERVER_ROOT."/classes/validate.class.php");
$Val = NEW VALIDATE;

if (empty($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
    case 'notify':
        include(SERVER_ROOT.'/sections/user/notify_edit.php');
        break;
    case 'notify_handle':
        include(SERVER_ROOT.'/sections/user/notify_handle.php');
        break;
    case 'notify_delete':
        authorize();
        if ($_GET['id'] && is_number($_GET['id'])) {
            $DB->query("DELETE FROM users_notify_filters WHERE ID='".db_string($_GET['id'])."' AND UserID='$LoggedUser[ID]'");
            $ArtistNotifications = $Cache->get_value('notify_artists_'.$LoggedUser['ID']);
            if (is_array($ArtistNotifications) && $ArtistNotifications['ID'] == $_GET['id']) {
                $Cache->delete_value('notify_artists_'.$LoggedUser['ID']);
            }
        }
        $Cache->delete_value('notify_filters_'.$LoggedUser['ID']);
        header('Location: user.php?action=notify');
        break;
    case 'search':// User search
        if (check_perms('admin_advanced_user_search') && check_perms('users_view_ips') && check_perms('users_view_email')) {
            include(SERVER_ROOT.'/sections/user/advancedsearch.php');
        }
        else {
            include(SERVER_ROOT.'/sections/user/search.php');
        }
        break;
    case 'edit':
        if (isset($_REQUEST['userid'])) {
            include(SERVER_ROOT.'/sections/user/edit.php');
        }
        else {
            header("Location: user.php?action=edit&userid={$LoggedUser['ID']}");
        }
        break;
    case '2fa':
        include(SERVER_ROOT . '/classes/google_authenticator.class.php');
        include(SERVER_ROOT . '/classes/qr.class.php');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_GET['do'])) {
            // we didn't get a required "do", we'll just let the 404 handler deal with the request.
            error(404);
        }

        $UserID = $_REQUEST['userid'];

        if (!is_number($UserID)) {
            error(404);
        }

        $DB->query("SELECT m.PassHash, m.Secret, m.2FA_Key, p.Level FROM users_main AS m LEFT JOIN permissions AS p ON p.ID = PermissionID WHERE m.ID = '" . db_string($UserID) . "'");

        list($PassHash, $Secret, $TFAKey, $Level) = $DB->next_record(MYSQLI_NUM);

        if ($UserID != $LoggedUser['ID'] && !check_perms('users_mod')) {
            error(403);
        }

        switch($_GET['do']) {
            case 'enable':
                if ($TFAKey) {
                    // 2fa is already enabled...
                    error(404);
                }

                if (empty($_SESSION['private_key'])) {
                    $_SESSION['private_key'] = (new PHPGangsta_GoogleAuthenticator())->createSecret();
                }

                include(SERVER_ROOT.'/sections/user/2fa/step1.php');
                break;

            case 'enable2':
                if ($TFAKey) {
                    // 2fa is already enabled...
                    error(404);
                }

                if (empty($_SESSION['private_key'])) {
                    header('Location: user.php?action=2fa&do=enable&userid=' . G::$LoggedUser['ID']);
                    exit;
                }

                if (empty($_POST['2fa'])) {
                    include(SERVER_ROOT.'/sections/user/2fa/step2.php');
                } else {
                    $works = (new PHPGangsta_GoogleAuthenticator())->verifyCode($_SESSION['private_key'], $_POST['2fa'], 2);

                    if (!$works) {
                        // user got their token wrong...
                        header('Location: user.php?action=2fa&do=enable&invalid&userid=' . $LoggedUser['ID']);
                    } else {
                        // user got their token right!
                        $key = $DB->escape_str($_SESSION['private_key']);

                        $recovery = [];

                        for ($i = 0; $i < 6; $i++) {
                            $recovery[] = strtoupper(bin2hex(openssl_random_pseudo_bytes(16)));
                        }

                        $recovery = serialize($recovery);

                        $DB->query("UPDATE users_main SET 2FA_Key = '{$key}', Recovery = '{$recovery}' WHERE ID = '{$UserID}'");
                        header('Location: user.php?action=2fa&do=complete&userid=' . $LoggedUser['ID']);
                    }
                }
                break;

            case 'complete':
                // user should only ever see this page once.
                if (empty($_SESSION['private_key'])) {
                    error(404);
                }

                include(SERVER_ROOT.'/sections/user/2fa/complete.php');
                unset($_SESSION['private_key']);
                break;

            case 'disable':
                if (!$TFAKey) {
                    // 2fa isn't enabled...
                    error(404);
                }

                if (empty($_POST['password']) && !check_perms('users_mod')) {
                    include(SERVER_ROOT.'/sections/user/2fa/password_confirm.php');
                } else {
                    if (check_perms('users_edit_password') || Users::check_password($_POST['password'], $PassHash)) {
                        $DB->query("UPDATE users_main SET 2FA_Key = '', Recovery = '' WHERE ID = '{$UserID}'");
                        if (isset($_GET['page']) && $_GET['page'] === 'user') {
                            $action = '';
                            $ID = $UserID;
                        }
                        else {
                            $action = 'action=edit&';
                            $ID = $LoggedUser['ID'];
                        }
                        header('Location: user.php?' . $action . 'userid=' . $ID);
                    }
                    else {
                        header('Location: user.php?action=2fa&do=disable&invalid&userid=' . $LoggedUser['ID']);
                        exit;
                    }
                }
                break;
        }
        break;
    case 'take_edit':
        include(SERVER_ROOT.'/sections/user/take_edit.php');
        break;
    case 'invitetree':
        include(SERVER_ROOT.'/sections/user/invitetree.php');
        break;
    case 'invite':
        include(SERVER_ROOT.'/sections/user/invite.php');
        break;
    case 'take_invite':
        include(SERVER_ROOT.'/sections/user/take_invite.php');
        break;
    case 'delete_invite':
        include(SERVER_ROOT.'/sections/user/delete_invite.php');
        break;
    case 'stats':
        include(SERVER_ROOT.'/sections/user/user_stats.php');
        break;
    case 'sessions':
        include(SERVER_ROOT.'/sections/user/sessions.php');
        break;
    case 'connchecker':
        include(SERVER_ROOT.'/sections/user/connchecker.php');
        break;
    case 'permissions':
        include(SERVER_ROOT.'/sections/user/permissions.php');
        break;
    case 'similar':
        include(SERVER_ROOT.'/sections/user/similar.php');
        break;
    case 'moderate':
        include(SERVER_ROOT.'/sections/user/takemoderate.php');
        break;
    case 'clearcache':
        if (!check_perms('admin_clear_cache') || !check_perms('users_override_paranoia')) {
            error(403);
        }
        $UserID = $_REQUEST['id'];
        $Cache->delete_value('user_info_'.$UserID);
        $Cache->delete_value('user_info_heavy_'.$UserID);
        $Cache->delete_value('subscriptions_user_new_'.$UserID);
        $Cache->delete_value('staff_pm_new_'.$UserID);
        $Cache->delete_value('inbox_new_'.$UserID);
        $Cache->delete_value('notifications_new_'.$UserID);
        $Cache->delete_value('collage_subs_user_new_'.$UserID);
        include(SERVER_ROOT.'/sections/user/user.php');
        break;

    // Provide public methods for Last.fm data gets.
    case 'lastfm_compare':
        if (isset($_GET['username'])) {
            echo LastFM::compare_user_with($_GET['username']);
        }
        break;
    case 'lastfm_last_played_track':
        if (isset($_GET['username'])) {
            echo LastFM::get_last_played_track($_GET['username']);
        }
        break;
    case 'lastfm_top_artists':
        if (isset($_GET['username'])) {
            echo LastFM::get_top_artists($_GET['username']);
        }
        break;
    case 'lastfm_top_albums':
        if (isset($_GET['username'])) {
            echo LastFM::get_top_albums($_GET['username']);
        }
        break;
    case 'lastfm_top_tracks':
        if (isset($_GET['username'])) {
            echo LastFM::get_top_tracks($_GET['username']);
        }
        break;
    case 'lastfm_clear_cache':
        if (isset($_GET['username']) && isset($_GET['uid'])) {
            echo LastFM::clear_cache($_GET['username'],$_GET['uid']);
        }
        break;
    case 'take_donate':
        break;
    case 'take_update_rank':
        break;
    default:
        if (isset($_REQUEST['id'])) {
            include(SERVER_ROOT.'/sections/user/user.php');
        } else {
            header("Location: user.php?id={$LoggedUser['ID']}");
        }
}
?>
