<?php

use Gazelle\Util\Crypto;

/*-- TODO ---------------------------//
Add the JavaScript validation into the display page using the class
//-----------------------------------*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow users to reset their password while logged in
if(!empty($LoggedUser['ID']) && $_REQUEST['act'] != 'recover') {
    header('Location: index.php');
    die();
}

if (BLOCK_OPERA_MINI && isset($_SERVER['HTTP_X_OPERAMINI_PHONE'])) {
    error('Opera Mini is banned. Please use another browser.');
}

// Check if IP is banned
$IPv4Man = new \Gazelle\Manager\IPv4;
if ($IPv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
    error('Your IP address has been banned.');
}

$Validate = new Validate;

if (array_key_exists('action', $_GET) && $_GET['action'] == 'disabled') {
    require('disabled.php');
    die();
}

if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'recover') {
    // Recover password
    if (!empty($_REQUEST['key'])) {
        // User has entered a new password, use step 2

        $DB->prepared_query("
            SELECT
                m.ID,
                m.Email,
                m.ipcc,
                i.ResetExpires
            FROM users_main as m
            INNER JOIN users_info AS i ON (i.UserID = m.ID)
            WHERE m.Enabled = '1'
                AND i.ResetKey != ''
                AND i.ResetKey = ?
                ", $_REQUEST['key']
        );
        list($UserID, $Email, $Country, $Expires) = $DB->next_record();
        if ($UserID && strtotime($Expires) > time()) {

        // If the user has requested a password change, and his key has not expired
            $Validate->SetFields('password', '1', 'regex', 'You entered an invalid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']);
            $Validate->SetFields('verifypassword', '1', 'compare', 'Your passwords did not match.', ['comparefield' => 'password']);

            if (!empty($_REQUEST['password'])) {
                // If the user has entered a password.
                // If the user has not entered a password, $Reset is not set to 1, and the success message is not shown
                $Err = $Validate->ValidateForm($_REQUEST);
                if ($Err == '') {
                    // Form validates without error, set new secret and password.
                    $DB->prepared_query("
                        UPDATE
                            users_main AS m,
                            users_info AS i
                        SET
                            i.ResetKey = '',
                            i.ResetExpires = NULL,
                            m.PassHash = ?
                        WHERE i.UserID = m.ID
                            AND m.ID = ?
                        ", Users::make_password_hash($_REQUEST['password']), $UserID
                    );
                    $DB->prepared_query('
                        INSERT INTO users_history_passwords
                               (UserID, ChangerIP, ChangeTime)
                        VALUES (?,      ?,         now())
                        ', $UserID, $_SERVER['REMOTE_ADDR']
                    );
                    $Reset = true; // Past tense form of "to reset", meaning that password has now been reset
                    logout_all_sessions($UserID);
                }
            }

            // Either a form asking for them to enter the password
            // Or a success message if $Reset is 1
            require('recover_step2.php');

        } else {
            // Either his key has expired, or he hasn't requested a pass change at all
            if (strtotime($Expires) < time() && $UserID) {
                // If his key has expired, clear all the reset information
                $DB->prepared_query("
                    UPDATE users_info SET
                        ResetKey = '',
                        ResetExpires = NULL
                    WHERE UserID = ?
                    ", $UserID
                );
                $_SESSION['reseterr'] = 'The link you were given has expired.'; // Error message to display on form
            }
            // Show him the first form (enter email address)
            header('Location: login.php?act=recover');
        }

    } // End step 2

    // User has not clicked the link in his email, use step 1
    else {
        $Validate->SetFields('email', '1', 'email', 'You entered an invalid email address.');

        if (!empty($_REQUEST['email'])) {
            // User has entered email and submitted form
            $Err = $Validate->ValidateForm($_REQUEST);

            if (!$Err) {
                // Form validates correctly
                $DB->prepared_query("
                    SELECT
                        ID,
                        Username,
                        Email
                    FROM users_main
                    WHERE Enabled = '1'
                        AND Email = ?
                    ", $_REQUEST['email']
                );
                list($UserID, $Username, $Email) = $DB->next_record();

                if ($UserID) {
                    // Email exists in the database
                    // Set ResetKey, send out email, and set $Sent to 1 to show success page
                    Users::resetPassword($UserID, $Username, $Email);

                    $Sent = 1; // If $Sent is 1, recover_step1.php displays a success message

                    //Log out all of the users current sessions
                    $Cache->delete_value("user_info_$UserID");
                    $Cache->delete_value("user_info_heavy_$UserID");
                    $Cache->delete_value("user_stats_$UserID");
                    $Cache->delete_value("enabled_$UserID");

                    $DB->prepared_query('
                        SELECT SessionID
                        FROM users_sessions
                        WHERE UserID = ?
                        ', $UserID
                    );
                    while (list($SessionID) = $DB->next_record()) {
                        $Cache->delete_value("session_$UserID"."_$SessionID");
                    }
                    $DB->prepared_query('
                        UPDATE users_sessions SET
                            Active = 0
                        WHERE Active = 1 AND UserID = ?
                        ', $UserID
                    );
                }
                $Err = "Email sent with further instructions.";
            }

        } elseif (!empty($_SESSION['reseterr'])) {
            // User has not entered email address, and there is an error set in session data
            // This is typically because their key has expired.
            // Stick the error into $Err so recover_step1.php can take care of it
            $Err = $_SESSION['reseterr'];
            unset($_SESSION['reseterr']);
        }

        // Either a form for the user's email address, or a success message
        require('recover_step1.php');
    } // End if (step 1)

} // End password recovery
elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === '2fa_recovery') {
    if (!isset($_SESSION['temp_user_data'])) {
        header('Location: login.php');
        exit;
    }
    elseif (empty($_POST['2fa_recovery_key'])) {
        require('2fa_recovery.php');
    }
    else {
        list($UserID, $PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey, $Recovery) = $_SESSION['temp_user_data'];
        $Recovery = (!empty($Recovery)) ? unserialize($Recovery) : [];
        if (($Key = array_search($_POST['2fa_recovery_key'], $Recovery)) !== false) {
            $SessionID = randomString();
            $Cookie = Crypto::encrypt(Crypto::encrypt($SessionID . '|~|' . $UserID, ENCKEY), ENCKEY);
            if ($_SESSION['temp_stay_logged']) {
                $KeepLogged = '1';
                setcookie('session', $Cookie, time() + 60 * 60 * 24 * 365, '/', '', $SSL, true);
            } else {
                $KeepLogged = '0';
                setcookie('session', $Cookie, 0, '/', '', $SSL, true);
            }

            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);

            //TODO: another tracker might enable this for donors, I think it's too stupid to bother adding that
            // Because we <3 our staff
            $Permissions = Permissions::get_permissions($PermissionID);
            $CustomPermissions = unserialize($CustomPermissions);
            if (isset($Permissions['Permissions']['site_disable_ip_history'])
                || isset($CustomPermissions['site_disable_ip_history'])
            ) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }

            $DB->prepared_query('
                INSERT INTO users_sessions
                       (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
                VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
                ', $UserID, $SessionID, $KeepLogged, $Browser, $OperatingSystem, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            );

            $Cache->begin_transaction("users_sessions_$UserID");
            $Cache->insert_front($SessionID, [
                'SessionID' => $SessionID,
                'Browser' => $Browser,
                'OperatingSystem' => $OperatingSystem,
                'IP' => $_SERVER['REMOTE_ADDR'],
                'LastUpdate' => sqltime()
            ]);
            $Cache->commit_transaction(0);

            unset($Recovery[$Key]);
            $DB->prepared_query('
                UPDATE users_main SET
                    Recovery = ?
                WHERE ID = ?
                ', serialize($Recovery), $UserID
            );
            $DB->prepared_query('
                INSERT INTO user_last_access
                       (user_id, last_access)
                VALUES (?, now())
                ON DUPLICATE KEY UPDATE last_access = now()
                ', $UserID
            );

            if (!empty($_COOKIE['redirect'])) {
                $URL = $_COOKIE['redirect'];
                setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                header("Location: $URL");
                die();
            } else {
                header('Location: index.php');
                die();
            }
        }
        else {
            $DB->prepared_query('
                SELECT ID, Attempts, Bans, BannedUntil
                FROM login_attempts
                WHERE IP = ?
                ', $_SERVER['REMOTE_ADDR']
            );
            list($AttemptID, $Attempts, $Bans, $BannedUntil) = $DB->next_record();
            if ($BannedUntil == '') {
                $BannedUntil = null;
            }

            // Function to log a user's login attempt
            function log_attempt($UserID) {
                global $DB, $Cache, $AttemptID, $Attempts, $Bans, $BannedUntil;
                if ($AttemptID) { // User has attempted to log in recently
                    $Attempts++;
                    if ($Attempts > 5) { // Only 6 allowed login attempts, ban user's IP
                        $BannedUntil = time_plus(60 * 60 * 6);
                        $DB->prepared_query('
                            UPDATE login_attempts SET
                                Bans = Bans + 1,
                                LastAttempt = now(),
                                Attempts = ?,
                                BannedUntil = ?
                            WHERE ID = ?
                            ', $Attempts, $BannedUntil, $AttemptID
                        );

                        if ($Bans > 9) { // Automated bruteforce prevention
                            $IPv4Man = new \Gazelle\Manager\IPv4;
                            $IPv4Man->createBan($UserID, $IPStr, $IPStr, 'Automated ban per >60 failed login attempts');
                        }
                    } else {
                        // User has attempted fewer than 6 logins
                        $DB->prepared_query('
                            UPDATE login_attempts SET
                                LastAttempt = now(),
                                BannedUntil = NULL,
                                Attempts = ?
                            WHERE ID = ?
                            ', $Attempts, $AttemptID
                        );
                    }
                } else { // User has not attempted to log in recently
                    $Attempts = 1;
                    $DB->prepared_query('
                        INSERT INTO login_attempts
                               (UserID, IP)
                        VALUES (?,      ?)
                        ', $UserID, $IPStr
                    );
                }
            } // end log_attempt function
            log_attempt($UserID);
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            header('Location: login.php');
        }
    }
}
elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === '2fa') {
    if (!isset($_SESSION['temp_user_data'])) {
        header('Location: login.php');
        exit;
    }

    if (empty($_POST['2fa'])) {
        require('2fa.php');
    } else {
        include(SERVER_ROOT . '/classes/google_authenticator.class.php');

        list($UserID, $PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey, $Recovery) = $_SESSION['temp_user_data'];

        if (!(new PHPGangsta_GoogleAuthenticator())->verifyCode($TFAKey, $_POST['2fa'], 2)) {
            // invalid 2fa key, log the user completely out
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            header('Location: login.php?invalid2fa');
        } else {
            $SessionID = randomString();
            $Cookie = Crypto::encrypt(Crypto::encrypt($SessionID . '|~|' . $UserID, ENCKEY), ENCKEY);

            if ($_SESSION['temp_stay_logged']) {
                $KeepLogged = '1';
                setcookie('session', $Cookie, time() + 60 * 60 * 24 * 365, '/', '', $SSL, true);
            } else {
                $KeepLogged = '0';
                setcookie('session', $Cookie, 0, '/', '', $SSL, true);
            }

            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);

            //TODO: another tracker might enable this for donors, I think it's too stupid to bother adding that
            // Because we <3 our staff
            $Permissions = Permissions::get_permissions($PermissionID);
            $CustomPermissions = unserialize($CustomPermissions);
            if (isset($Permissions['Permissions']['site_disable_ip_history'])
                || isset($CustomPermissions['site_disable_ip_history'])
            ) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }

            $DB->prepared_query('
                INSERT INTO users_sessions
                       (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
                VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
                ', $UserID, $SessionID, $KeepLogged, $Browser, $OperatingSystem, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            );

            $Cache->begin_transaction("users_sessions_$UserID");
            $Cache->insert_front($SessionID, [
                'SessionID' => $SessionID,
                'Browser' => $Browser,
                'OperatingSystem' => $OperatingSystem,
                'IP' => $_SERVER['REMOTE_ADDR'],
                'LastUpdate' => sqltime()
            ]);
            $Cache->commit_transaction(0);

            $DB->prepared_query('
                INSERT INTO user_last_access
                       (user_id, last_access)
                VALUES (?, now())
                ON DUPLICATE KEY UPDATE last_access = now()
                ', $UserID
            );

            if (!empty($_COOKIE['redirect'])) {
                $URL = $_COOKIE['redirect'];
                setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                header("Location: $URL");
                die();
            } else {
                header('Location: index.php');
                die();
            }
        }
    }
}
// Normal login
else {
    if (isset($_SESSION['temp_user_data'])) {
        header('Location: login.php?act=2fa');
        exit;
    }

    $Validate->SetFields('username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]);
    $Validate->SetFields('password', '1', 'string', 'You entered an invalid password.', ['minlength' => '6', 'maxlength' => -1]);

    $DB->prepared_query('
        SELECT ID, Attempts, Bans, BannedUntil
        FROM login_attempts
        WHERE IP = ?
        ', $_SERVER['REMOTE_ADDR']
    );
    list($AttemptID, $Attempts, $Bans, $BannedUntil) = $DB->next_record();

    // Function to log a user's login attempt
    function log_attempt($UserID) {
        global $DB, $Cache, $AttemptID, $Attempts, $Bans, $BannedUntil;
        $IPStr = $_SERVER['REMOTE_ADDR'];
        if ($AttemptID) { // User has attempted to log in recently
            $Attempts++;
            if ($Attempts > 5) { // Only 6 allowed login attempts, ban user's IP
                $BannedUntil = time_plus(60 * 60 * 6);
                $DB->prepared_query('
                    UPDATE login_attempts SET
                        Bans = Bans + 1,
                        LastAttempt = now(),
                        Attempts = ?,
                        BannedUntil = now() + INTERVAL 6 HOUR
                    WHERE ID = ?
                    ', $Attempts, $BannedUntil, $AttemptID
                );

                if ($Bans > 9) { // Automated bruteforce prevention
                    $IPv4Man = new \Gazelle\Manager\IPv4;
                    $IPv4Man->createBan($UserID, $IPStr, $IPStr, 'Automated ban per >60 failed login attempts');
                }
            } else {
                // User has attempted fewer than 6 logins
                $DB->prepared_query('
                    UPDATE login_attempts SET
                        LastAttempt = now(),
                        BannedUntil = NULL,
                        Attempts = ?
                    WHERE ID = ?
                    ', $Attempts, $AttemptID
                );
            }
        } else { // User has not attempted to log in recently
            $Attempts = 1;
            if (!$UserID) {
                $UserID = 0;
            }
            $DB->prepared_query('
                INSERT INTO login_attempts
                       (UserID, IP)
                VALUES (?,      ?)
                ', $UserID, $IPStr
            );
        }
    } // end log_attempt function

    // If user has submitted form
    if (isset($_POST['username']) && !empty($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])) {
        if (strtotime($BannedUntil) > time()) {
            header("Location: login.php");
            die();
        }
        $Err = $Validate->ValidateForm($_POST);

        if (!$Err) {
            // Passes preliminary validation (username and password "look right")
            $DB->prepared_query("
                SELECT
                    ID,
                    PermissionID,
                    CustomPermissions,
                    PassHash,
                    Enabled,
                    2FA_Key,
                    Recovery
                FROM users_main
                WHERE Username != '' and Username = ?
                ", $_POST['username']
            );
            $UserData = $DB->next_record(MYSQLI_NUM, [2, 6]);
            list($UserID, $PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey) = $UserData;
            if (strtotime($BannedUntil) < time()) {
                if ($UserID && Users::check_password($_POST['password'], $PassHash)) {
                    if (password_needs_rehash($PassHash, PASSWORD_DEFAULT) || Users::check_password_old($_POST['password'], $PassHash)) {
                        $DB->prepared_query('
                            UPDATE users_main SET
                                passhash = ?
                            WHERE ID = ?
                            ', Users::make_password_hash($_POST['password']), $UserID
                        );
                    }

                    if ($Enabled == 1) {
                        $SessionID = randomString();
                        $Cookie = Crypto::encrypt(Crypto::encrypt($SessionID . '|~|' . $UserID, ENCKEY), ENCKEY);

                        if ($TFAKey) {
                            // user has TFA enabled! :)
                            $_SESSION['temp_stay_logged'] = (isset($_POST['keeplogged']) && $_POST['keeplogged']);
                            $_SESSION['temp_user_data'] = $UserData;
                            header('Location: login.php?act=2fa');
                            exit;
                        }

                        if (isset($_POST['keeplogged']) && $_POST['keeplogged']) {
                            $KeepLogged = '1';
                            setcookie('session', $Cookie, time() + 60 * 60 * 24 * 365, '/', '', $SSL, true);
                        } else {
                            $KeepLogged = '0';
                            setcookie('session', $Cookie, 0, '/', '', $SSL, true);
                        }

                        //TODO: another tracker might enable this for donors, I think it's too stupid to bother adding that
                        // Because we <3 our staff
                        $Permissions = Permissions::get_permissions($PermissionID);
                        $CustomPermissions = unserialize($CustomPermissions);
                        if (isset($Permissions['Permissions']['site_disable_ip_history'])
                            || isset($CustomPermissions['site_disable_ip_history'])
                        ) {
                            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
                        }

                        $DB->prepared_query('
                            INSERT INTO users_sessions
                                   (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
                            VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
                            ', $UserID, $SessionID, $KeepLogged, $Browser, $OperatingSystem, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
                        );
                        $Cache->begin_transaction("users_sessions_$UserID");
                        $Cache->insert_front($SessionID, [
                            'SessionID' => $SessionID,
                            'Browser' => $Browser,
                            'OperatingSystem' => $OperatingSystem,
                            'IP' => $_SERVER['REMOTE_ADDR'],
                            'LastUpdate' => sqltime()
                        ]);
                        $Cache->commit_transaction(0);

                        $DB->prepared_query('
                            INSERT INTO user_last_access
                                   (user_id, last_access)
                            VALUES (?, now())
                            ON DUPLICATE KEY UPDATE last_access = now()
                            ', $UserID
                        );
                        if (!empty($_COOKIE['redirect'])) {
                            $URL = $_COOKIE['redirect'];
                            setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                            header("Location: $URL");
                            die();
                        } else {
                            header('Location: index.php');
                            die();
                        }
                    } else {
                        log_attempt($UserID);
                        if ($Enabled == 2) {

                            // Save the username in a cookie for the disabled page
                            setcookie('username', db_string($_POST['username']), time() + 60 * 60, '/', '', false);
                            header('Location: login.php?action=disabled');
                        } elseif ($Enabled == 0) {
                            $Err = 'Your account has not been confirmed.<br />Please check your email.';
                        }
                        setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                    }
                } else {
                    log_attempt($UserID);

                    $Err = 'Your username or password was incorrect.';
                    setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                }

            } else {
                log_attempt($UserID);
                setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
            }

        } else {
            log_attempt('0');
            setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
        }
    }
    require('sections/login/login.php');
}
