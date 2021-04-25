<?php

use Gazelle\Util\Mail;

class AutoEnable {

    // Constants for database values
    const APPROVED = 1;
    const DENIED = 2;
    const DISCARDED = 3;

    // Cache key to store the number of enable requests
    const CACHE_KEY_NAME = 'num_enable_requests';

    // The default request rejected message
    const REJECTED_MESSAGE = "Your request to re-enable your account has been rejected.<br />This may be because a request is already pending for your username, or because a recent request was denied.<br /><br />You are encouraged to discuss this with staff by visiting %s on %s";

    // The default request received message
    const RECEIVED_MESSAGE = "Your request to re-enable your account has been received. You can expect a reply message in your email within 48 hours.<br />If you do not receive an email after 48 hours have passed, please visit us on IRC for assistance.";

    /**
     * Handle a new enable request
     *
     * @param string $Username The user's username
     * @param string $Email The user's email address
     * @return string The output
     */
    public static function new_request($Username, $Email) {
        if (empty($Username)) {
            header("Location: login.php");
            die();
        }

        // Make sure the user is allowed to make an enable request
        [$UserID, $requestExists] = G::$DB->row("
            SELECT um.ID,
                (uer.UserID IS NOT NULL) as requestExists
            FROM users_main AS um
            LEFT JOIN users_enable_requests AS uer ON (uer.UserID = um.ID)
            WHERE um.Enabled = '2'
                AND um.Username = ?
                AND (
                    uer.UserID IS NULL
                    OR (uer.Timestamp > now() - INTERVAL 1 WEEK AND uer.HandledTimestamp IS NULL)
                    OR (uer.Timestamp > now() - INTERVAL 2 MONTH AND uer.Outcome IN (?, ?))
                )
        ", $Username, self::DENIED, self::DISCARDED
        );

        $user = (new \Gazelle\Manager\User)->findById($UserID);
        $IP = $_SERVER['REMOTE_ADDR'];
        if (is_null($user)) {
            // say what?
            $Output = '';
        } elseif ($requestExists) {
            // User already has/had a pending activation request
            $Output = sprintf(self::REJECTED_MESSAGE, BOT_DISABLED_CHAN, BOT_SERVER);
            $user->addStaffNote("Enable request rejected from $IP")->modify();
        } else {
            // New disable activation request
            G::$DB->prepared_query("
                INSERT INTO users_enable_requests
                       (UserID, Email, IP, UserAgent, Timestamp)
                VALUES (?,      ?,     ?,  ?,         now())
                ", $UserID, $Email, $IP, $_SERVER['HTTP_USER_AGENT']
            );

            // Cache the number of requests for the modbar
            G::$Cache->increment_value(self::CACHE_KEY_NAME);
            setcookie('username', '', [
                'expires'  => time() + 60 * 60,
                'path'     => '/',
                'secure'   => !DEBUG_MODE,
                'httponly' => DEBUG_MODE,
                'samesite' => 'Lax',
            ]);
            $Output = self::RECEIVED_MESSAGE;
            $user->addStaffNote()->modify("Enable request " . G::$DB->inserted_id() . " received from $IP");
        }
        return $Output;
    }

    /*
     * Handle requests
     *
     * @param int|int[] $IDs An array of IDs, or a single ID
     * @param int $Status The status to mark the requests as
     * @param string $Comment The staff member comment
     */
    public static function handle_requests($IDs, $Status, $Comment) {
        if ($Status != self::APPROVED && $Status != self::DENIED && $Status != self::DISCARDED) {
            error(404);
        }

        $UserInfo = [];
        $IDs = (!is_array($IDs)) ? [$IDs] : $IDs;
        if (empty($IDs)) {
            error(404);
        }
        foreach ($IDs as $ID) {
            if (!is_number($ID)) {
                error(404);
            }
        }

        G::$DB->prepared_query("
            SELECT Email, ID, UserID
            FROM users_enable_requests
            WHERE Outcome IS NULL
                AND ID IN (" . placeholders($IDs) . ")
            ", ...$IDs
        );
        $Results = G::$DB->to_array(false, MYSQLI_NUM);

        if ($Status === self::DISCARDED) {
            foreach ($Results as $Result) {
                [, $ID, $UserID] = $Result;
                $UserInfo[] = [$ID, $UserID];
            }
        } else {
            // Prepare email
            if ($Status == self::APPROVED) {
                $subject  = "Your enable request for " . SITE_NAME . " has been approved";
                $template = 'email/enable_request_accepted.twig';
            } else {
                $subject  = "Your enable request for " . SITE_NAME . " has been denied";
                $template = 'email/enable_request_denied.twig';
            }

            foreach ($Results as $Result) {
                [$Email, $ID, $UserID] = $Result;
                $UserInfo[] = [$ID, $UserID];

                if ($Status != self::APPROVED) {
                    $token = '';
                } else {
                    // Generate token
                    $token = randomString();
                    G::$DB->prepared_query("
                        UPDATE users_enable_requests SET
                            Token = ?
                        WHERE ID = ?
                        ", $token, $ID
                    );
                }
                global $Twig;
                (new Mail)->send($Email, $subject, $Twig->render($template, ['token' => $token]));
            }
        }

        // User notes stuff
        foreach ($UserInfo as $User) {
            [$ID, $UserID] = $User;
            (new \Gazelle\User($UserID))->addStaffNote(
                "Enable request $ID " . strtolower(self::get_outcome_string($Status))
                    . ' by [user]' . G::$LoggedUser['Username'] . '[/user]' . (!empty($Comment) ? "\nReason: $Comment" : "")
            )->modify();
        }

        // Update database values and decrement cache
        G::$DB->prepared_query("
            UPDATE users_enable_requests SET
                HandledTimestamp = now(),
                CheckedBy = ?,
                Outcome = ?
            WHERE ID IN (" . placeholders($IDs) . ")
            ", G::$LoggedUser['ID'], $Status, ...$IDs
        );
        G::$Cache->decrement_value(self::CACHE_KEY_NAME, count($IDs));
    }

    /**
     * Unresolve a discarded request
     *
     * @param int $ID The request ID
     */
    public static function unresolve_request($ID) {
        $ID = (int) $ID;
        if (!$ID) {
            error(404);
        }
        $user = (new \Gazelle\Manager\User)->findById(
            G::$DB->scalar("
                SELECT UserID
                FROM users_enable_requests
                WHERE Outcome = ?
                    AND ID = ?
                ", self::DISCARDED, $ID
            )
        );
        if (is_null($user)) {
            error(404);
        }

        $user->addStaffNote("Enable request $ID unresolved by [user]" . G::$LoggedUser['Username'] . '[/user]')->modify();
        G::$DB->prepared_query("
            UPDATE users_enable_requests SET
                Outcome = NULL,
                HandledTimestamp = NULL,
                CheckedBy = NULL
            WHERE ID = ?
            ", $ID
        );
        G::$Cache->increment_value(self::CACHE_KEY_NAME);
    }

    /**
     * Get the corresponding outcome string for a numerical value
     *
     * @param int $Outcome The outcome integer
     * @return string The formatted output string
     */
    public static function get_outcome_string($Outcome): string {
        if ($Outcome == self::APPROVED) {
            $String = "Approved";
        } else if ($Outcome == self::DENIED) {
            $String = "Rejected";
        } else if ($Outcome == self::DISCARDED) {
            $String = "Discarded";
        } else {
            $String = "---";
        }
        return $String;
    }

    /**
     * Handle a user's request to enable an account
     *
     * @param string $Token The token
     * @return string The error output, or an empty string
     */
    public static function handle_token($Token) {
        [$UserID, $Timestamp] = G::$DB->row("
            SELECT UserID, HandledTimestamp
            FROM users_enable_requests
            WHERE Token = ?
            ", $Token
        );
        if (!$UserID) {
            $Err = "Invalid token.";
        } else {
            G::$DB->query("
                UPDATE users_enable_requests SET Token = NULL WHERE Token = ?
                ", $Token
            );
            if ($Timestamp < time_minus(3600 * 48)) {
                // Old request
                (new \Gazelle\User($UserID))->addStaffNote("Tried to use an expired enable token from ".$_SERVER['REMOTE_ADDR'])->modify();
                $Err = "Token has expired. Please visit ".BOT_DISABLED_CHAN." on ".BOT_SERVER." to discuss this with staff.";
            } else {
                // Good request, decrement cache value and enable account
                G::$Cache->decrement_value(AutoEnable::CACHE_KEY_NAME);
                G::$DB->prepared_query("UPDATE users_main SET Enabled = '1', can_leech = '1' WHERE ID = ?", $UserID);
                G::$DB->prepared_query("UPDATE users_info SET BanReason = '0' WHERE UserID = ?", $UserID);
                Tracker::update_tracker('add_user', [
                    'id' => $UserID,
                    'passkey' => G::$DB->scalar("SELECT torrent_pass FROM users_main WHERE ID = ?", $UserID)
                ]);
                $Err = "Your account has been enabled. You may now log in.";
            }
        }
        return $Err;
    }

    /**
     * Build the search query, from the searchbox inputs
     *
     * @param int $UserID The user ID
     * @param string $IP The IP
     * @param string $SubmittedTimestamp The timestamp representing when the request was submitted
     * @param int $HandledUserID The ID of the user that handled the request
     * @param string $HandledTimestamp The timestamp representing when the request was handled
     * @param int $OutcomeSearch The outcome of the request
     * @param boolean $Checked Should checked requests be included?
     * @return array The WHERE conditions for the query
     */
    public static function build_search_query($Username, $IP, $SubmittedBetween, $SubmittedTimestamp1, $SubmittedTimestamp2, $HandledUsername, $HandledBetween, $HandledTimestamp1, $HandledTimestamp2, $OutcomeSearch, $Checked) {
        $cond = [];
        $args = [];

        if (!empty($Username)) {
            $cond[] = "um1.Username = ?";
            $args[] = $Username;
        }

        if (!empty($IP)) {
            $cond[] = "uer.IP = ?";
            $args[] = $IP;
        }

        if (!empty($SubmittedTimestamp1)) {
            switch($SubmittedBetween) {
                case 'on':
                    $cond[] = "DATE(uer.Timestamp) = DATE(?)";
                    $args[] = $SubmittedTimestamp1;
                    break;
                case 'before':
                    $cond[] = "DATE(uer.Timestamp) < DATE(?)";
                    $args[] = $SubmittedTimestamp1;
                    break;
                case 'after':
                    $cond[] = "DATE(uer.Timestamp) > DATE(?)";
                    $args[] = $SubmittedTimestamp1;
                    break;
                case 'between':
                    if (!empty($SubmittedTimestamp2)) {
                        $cond[] = "DATE(uer.Timestamp) BETWEEN DATE(?) AND DATE(?)";
                        $args[] = $SubmittedTimestamp1;
                        $args[] = $SubmittedTimestamp2;
                    }
                    break;
                default:
                    break;
            }
        }

        if (!empty($HandledTimestamp1)) {
            switch($HandledBetween) {
                case 'on':
                    $cond[] = "DATE(uer.HandledTimestamp) = DATE(?)";
                    $args[] = $HandledTimestamp1;
                    break;
                case 'before':
                    $cond[] = "DATE(uer.HandledTimestamp) < DATE(?)";
                    $args[] = $HandledTimestamp1;
                    break;
                case 'after':
                    $cond[] = "DATE(uer.HandledTimestamp) > DATE(?)";
                    $args[] = $HandledTimestamp1;
                    break;
                case 'between':
                    if (!empty($HandledTimestamp2)) {
                        $cond[] = "DATE(uer.HandledTimestamp) BETWEEN DATE(?) AND DATE(?)";
                        $args[] = $HandledTimestamp1;
                        $args[] = $HandledTimestamp2;
                    }
                    break;
                default:
                    break;
            }
        }

        if (!empty($HandledUsername)) {
            $cond[] = "um2.Username = ?";
            $args[] = $HandledUsername;
        }

        if (!empty($OutcomeSearch)) {
            $cond[] = "uer.Outcome = ?";
            $args[] = $OutcomeSearch;
        }

        if ($Checked) {
            // This is to skip the if statement in enable_requests.php
            $cond[] = "(uer.Outcome IS NULL OR uer.Outcome IS NOT NULL)";
        }

        return [$cond, $args];
    }
}
