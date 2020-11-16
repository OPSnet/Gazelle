<?php

function log_attempt(int $UserID, string $capture) {
    global $AttemptID, $Attempts, $Bans, $BannedUntil, $watch;
    $IPStr = $_SERVER['REMOTE_ADDR'];
    if (!$AttemptID) {
        $AttemptID = $watch->create($IPStr, $capture, $UserID);
    } elseif ($Attempts < 6) {
        $watch->setWatch($AttemptID)->increment($UserID, $IPStr, $capture);
    } else {
        $watch->setWatch($AttemptID)->ban($Attempts, $capture, $UserID);
        if ($Bans > 9) {
            $IPv4Man = new Gazelle\Manager\IPv4;
            $IPv4Man->createBan($UserID, $IPStr, $IPStr, 'Automated ban, too many failed login attempts');
        }
        Misc::send_pm($UserID, 0, "Too many login attempts on your account",
            G::$Twig->render('login/too-many-failures.twig', [
            'ipaddr' => $IPStr,
            'username' => $capture,
        ]));
    }
    $Attempts = $watch->nrAttempts();
    $BannedUntil = $watch->bannedUntil();
}

function needResetIpaddr(int $permissionId, $custom = ''): bool {
    $perms = Permissions::get_permissions($permissionId);
    $custom = unserialize($custom);
    return isset($perms['Permissions']['site_disable_ip_history']) || isset($custom['site_disable_ip_history']);
}

/*-- TODO ---------------------------//
Add the JavaScript validation into the display page using the class
//-----------------------------------*/

if (BLOCK_OPERA_MINI && isset($_SERVER['HTTP_X_OPERAMINI_PHONE'])) {
    error('Opera Mini is banned. Please use another browser.');
}

// Allow users to reset their password while logged in
if(!empty($LoggedUser['ID']) && $_REQUEST['act'] != 'recover') {
    header('Location: index.php');
    die();
}

// Check if IP is banned
$IPv4Man = new \Gazelle\Manager\IPv4;
if ($IPv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
    error('Your IP address has been banned.');
}

if (array_key_exists('action', $_GET) && $_GET['action'] == 'disabled') {
    require('disabled.php');
    die();
}

$watch = new Gazelle\LoginWatch;

if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'recover') {
    // Recover password
    if (!empty($_REQUEST['key'])) {
        // User has entered a new password, use step 2
        [$UserID, $Email, $Country, $Expires] = $DB->row("
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
        if (!$UserID || strtotime($Expires) < time()) {
            // Either the key has expired, or they didn't request a password change at all
            if ($UserID) {
                // If the key has expired, clear all the reset information
                $DB->prepared_query("
                    UPDATE users_info SET
                        ResetKey = '',
                        ResetExpires = NULL
                    WHERE UserID = ?
                    ", $UserID
                );
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['reseterr'] = 'The link you were given has expired.'; // Error message to display on form
                session_write_close();
            }
            // Show the first form (enter email address)
            header('Location: login.php?act=recover');
            exit;
        } else {
            // The user requested a password change and the key has not expired
            $Validate = new Validate;
            $Validate->SetFields('password', '1', 'regex',
                'You entered an invalid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer',
                ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']);
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
                        ", Gazelle\UserCreator::hashPassword($_REQUEST['password']), $UserID
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
            // Or a success message if $Reset is true
            require('recover_step2.php');
        }
        // End step 2
    } else {
        // User has not clicked the link in his email, use step 1
        $Validate = new Validate;
        $Validate->SetFields('email', '1', 'email', 'You entered an invalid email address.');
        if (!empty($_REQUEST['email'])) {
            // User has entered email and submitted form
            $Err = $Validate->ValidateForm($_REQUEST);

            if (!$Err) {
                // Email exists in the database?
                [$UserID, $Username, $Email] = $DB->row("
                    SELECT
                        ID,
                        Username,
                        Email
                    FROM users_main
                    WHERE Enabled = '1'
                        AND Email = ?
                    ", $_REQUEST['email']
                );

                if ($UserID) {
                    // Set ResetKey, send out email, and set $Sent to 1 to show success page
                    Users::resetPassword($UserID, $Username, $Email);
                    $session = new Gazelle\Session($UserID);
                    $session->dropAll();
                    $Sent = 1; // If $Sent is 1, recover_step1.php displays a success message
                }
                $Err = "Email sent with further instructions.";
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!empty($_SESSION['reseterr'])) {
                // User has not entered email address, and there is an error set in session data
                // This is typically because their key has expired.
                // Stick the error into $Err so recover_step1.php can take care of it
                $Err = $_SESSION['reseterr'];
                unset($_SESSION['reseterr']);
            }
            session_write_close();
        }
        // Either a form for the user's email address, or a success message
        require('recover_step1.php');
    } // End if (step 1)
    // End password recovery

} elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === '2fa_recovery') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['read_and_close' => true]);
    }
    if (!isset($_SESSION['temp_user_data'])) {
        header('Location: login.php');
        exit;
    } elseif (empty($_POST['2fa_recovery_key'])) {
        [$AttemptID, $Attempts, $Bans, $BannedUntil] = $watch->findByIp($_SERVER['REMOTE_ADDR']);
        require('2fa_recovery.php');
    } else {
        [$UserID, $capture] = $_SESSION['temp_user_data'];
        [$PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey, $Recovery] = $DB->row("
            SELECT
                PermissionID,
                CustomPermissions,
                PassHash,
                Enabled,
                2FA_Key,
                Recovery
            FROM users_main
            WHERE ID = ?
            ", $UserID
        );
        $Recovery = (!empty($Recovery)) ? unserialize($Recovery) : [];
        if (($Key = array_search($_POST['2fa_recovery_key'], $Recovery)) === false) {
            [$AttemptID, $Attempts, $Bans, $BannedUntil] = $watch->findByIp($_SERVER['REMOTE_ADDR']);
            log_attempt($UserID, $capture);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            session_write_close();
            $Err = 'Your backup recovery key was incorrect.';
            setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
            require('sections/login/login.php');
            exit;
        } else {
            if ($_SESSION['temp_stay_logged']) {
                $KeepLogged = '1';
                $expiry = time() + 60 * 60 * 24 * 365;
            } else {
                $KeepLogged = '0';
                $expiry = 0;
            }

            $sessionMan = new Gazelle\Session($UserID);
            $session = $sessionMan->create([
                'keep-logged' => $KeepLogged,
                'browser' => $Browser,
                'os' => $OperatingSystem,
                'ipaddr' => needResetIpaddr($PermissionID, $CustomPermissions) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'],
                'useragent' => $_SERVER['HTTP_USER_AGENT'],
            ]);
            setcookie('session', $sessionMan->cookie($session['SessionID']), $expiry, '/', '', $SSL, true);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            session_write_close();

            unset($Recovery[$Key]);
            $DB->prepared_query('
                UPDATE users_main SET
                    Recovery = ?
                WHERE ID = ?
                ', serialize($Recovery), $UserID
            );

            $watch->setWatch($AttemptID)->clearAttempts();
            if (empty($_COOKIE['redirect'])) {
                header('Location: index.php');
            } else {
                setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                header("Location: " . $_COOKIE['redirect']);
            }
            exit;
        }
    }
} elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === '2fa') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['read_and_close' => true]);
    }
    if (!isset($_SESSION['temp_user_data'])) {
        header('Location: login.php');
        exit;
    }
    if (empty($_POST['2fa'])) {
        require('2fa.php');
    } else {
        [$UserID, $capture] = $_SESSION['temp_user_data'];
        [$PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey, $Recovery] = $DB->row("
            SELECT
                PermissionID,
                CustomPermissions,
                PassHash,
                Enabled,
                2FA_Key,
                Recovery
            FROM users_main
            WHERE ID = ?
            ", $UserID
        );

        if (!(new \RobThree\Auth\TwoFactorAuth())->verifyCode($TFAKey, $_POST['2fa'], 2)) {
            // invalid 2fa key, log the user completely out
            [$AttemptID, $Attempts, $Bans, $BannedUntil] = $watch->findByIp($_SERVER['REMOTE_ADDR']);
            log_attempt($UserID, $capture);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            session_write_close();
            header('Location: login.php?invalid2fa');
        } else {
            if ($_SESSION['temp_stay_logged']) {
                $KeepLogged = '1';
                $expiry = time() + 60 * 60 * 24 * 365;
            } else {
                $KeepLogged = '0';
                $expiry = 0;
            }

            $sessionMan = new Gazelle\Session($UserID);
            $session = $sessionMan->create([
                'keep-logged' => $KeepLogged,
                'browser' => $Browser,
                'os' => $OperatingSystem,
                'ipaddr' => needResetIpaddr($PermissionID, $CustomPermissions) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'],
                'useragent' => $_SERVER['HTTP_USER_AGENT'],
            ]);
            setcookie('session', $sessionMan->cookie($session['SessionID']), $expiry, '/', '', $SSL, true);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['temp_stay_logged'], $_SESSION['temp_user_data']);
            session_write_close();

            $watch->setWatch($AttemptID)->clearAttempts();
            if (empty($_COOKIE['redirect'])) {
                header('Location: index.php');
            } else {
                setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                header("Location: " . $_COOKIE['redirect']);
            }
        }
        exit;
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['read_and_close' => true]);
    }
    if (isset($_SESSION['temp_user_data'])) {
        header('Location: login.php?act=2fa');
        exit;
    }

    // Normal login
    [$AttemptID, $Attempts, $Bans, $BannedUntil] = $watch->findByIp($_SERVER['REMOTE_ADDR']);
    if (!isset($_POST['username']) && !isset($_POST['password'])) {
        if ($Attempts > 5 && !$BannedUntil) {
            $watch->setWatch($AttemptID)->ban($Attempts, '-initial page load-');
            $BannedUntil = $watch->bannedUntil();
        }
    } else {
        // If user has submitted form
        if (strtotime($BannedUntil) > time()) {
            header("Location: login.php");
            exit;
        }
        $Validate = new Validate;
        $Validate->SetFields('username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]);
        $Validate->SetFields('password', '1', 'string', 'You entered an invalid password.', ['minlength' => '6', 'maxlength' => -1]);
        $Err = $Validate->ValidateForm($_POST);

        $username = trim($_POST['username']);
        if ($Err) {
            log_attempt(0, $username);
            setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
        } else {
            // username and password "look right", see if they are valid
            $password = $_POST['password'];
            [$UserID, $PermissionID, $CustomPermissions, $PassHash, $Enabled, $TFAKey] = $DB->row("
                SELECT
                    ID,
                    PermissionID,
                    CustomPermissions,
                    PassHash,
                    Enabled,
                    2FA_Key
                FROM users_main
                WHERE Username = ?
                ", $username
            );
            if (strtotime($BannedUntil) >= time()) {
                log_attempt($UserID ?? 0, $username);
                setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
            } elseif ($Attempts > 5 && !$BannedUntil) {
                $watch->ban($Attempts, $username);
                $BannedUntil = $watch->bannedUntil();
            } else {
                if (!($UserID && Users::check_password($password, $PassHash))) {
                    log_attempt($UserID ?? 0, $username);
                    $Err = 'Your username or password was incorrect.';
                    setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                } else {
                    if (password_needs_rehash($PassHash, PASSWORD_DEFAULT) || Users::check_password_old($password, $PassHash)) {
                        $DB->prepared_query('
                            UPDATE users_main SET
                                PassHash = ?
                            WHERE ID = ?
                            ', Gazelle\UserCreator::hashPassword($password), $UserID
                        );
                    }

                    if ($Enabled == '0') {
                        log_attempt($UserID, $username);
                        $Err = 'Your account has not been confirmed.<br />Please check your email.';
                        setcookie('keeplogged', '', time() + 60 * 60 * 24 * 365, '/', '', false);
                    } elseif ($Enabled == '2') {
                        log_attempt($UserID, $username);
                        // Save the username in a cookie for the disabled page
                        setcookie('username', urlencode($username), time() + 60 * 60, '/', '', false);
                        header('Location: login.php?action=disabled');
                    } elseif ($Enabled == '1') {
                        $KeepLogged = (isset($_POST['keeplogged']) && $_POST['keeplogged']);
                        if ($TFAKey) {
                            // user has TFA enabled! :)
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            $_SESSION['temp_stay_logged'] = $KeepLogged;
                            $_SESSION['temp_user_data'] = [$UserID, $username];
                            session_write_close();
                            header('Location: login.php?act=2fa');
                        } else {
                            if ($KeepLogged) {
                                $expiry = time() + 60 * 60 * 24 * 365;
                            } else {
                                $expiry = 0;
                            }

                            $sessionMan = new Gazelle\Session($UserID);
                            $session = $sessionMan->create([
                                'keep-logged' => $KeepLogged ? '1' : '0',
                                'browser' => $Browser,
                                'os' => $OperatingSystem,
                                'ipaddr' => needResetIpaddr($PermissionID, $CustomPermissions) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'],
                                'useragent' => $_SERVER['HTTP_USER_AGENT'],
                            ]);
                            setcookie('session', $sessionMan->cookie($session['SessionID']), $expiry, '/', '', $SSL, true);

                            $watch->setWatch($AttemptID)->clearAttempts();
                            if (empty($_COOKIE['redirect'])) {
                                header('Location: index.php');
                            } else {
                                setcookie('redirect', '', time() - 60 * 60 * 24, '/', '', false);
                                header("Location: " . $_COOKIE['redirect']);
                            }
                        }
                        exit;
                    }
                }
            }
        }
    }
    require_once('login.php');
}
