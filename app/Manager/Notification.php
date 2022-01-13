<?php

namespace Gazelle\Manager;

class Notification extends \Gazelle\Base {
    // Option types
    const OPT_PUSH = 3;
    const OPT_POPUP_PUSH = 4;
    const OPT_TRADITIONAL_PUSH = 5;

    // Types. These names must correspond to column names in users_notifications_settings
    const NEWS = 'News';
    const BLOG = 'Blog';
    const INBOX = 'Inbox';
    const QUOTES = 'Quotes';
    const GLOBALNOTICE = 'Global';

    /**
     * Send a push notification to a user
     *
     * @param array $UserIDs integer or array of integers of UserIDs to push
     * @param string $Title the title to be displayed in the push
     * @param string $Body the body of the push
     * @param string $URL url for the push notification to contain
     * @param string $Type what sort of push is it? PM, Quote, Announcement, etc
     */
    public function push($UserIDs, $Title, $Body, $URL = '', $Type = self::GLOBALNOTICE) {
        if (!is_array($UserIDs)) {
            $UserIDs = [$UserIDs];
        }
        foreach($UserIDs as $UserID) {
            $UserID = (int) $UserID;
            $QueryID = self::$db->get_query_id();
            $SQL = "
                SELECT
                    p.PushService, p.PushOptions
                FROM users_notifications_settings AS n
                    JOIN users_push_notifications AS p ON n.UserID = p.UserID
                WHERE n.UserID = '$UserID'
                AND p.PushService != 0";
            if ($Type != self::GLOBALNOTICE) {
                $SQL .= " AND n.$Type IN (" . self::OPT_PUSH . "," . self::OPT_POPUP_PUSH . "," . self::OPT_TRADITIONAL_PUSH . ")";
            }
            self::$db->prepared_query($SQL);

            if (self::$db->has_results()) {
                [$PushService, $PushOptions] = self::$db->next_record(MYSQLI_NUM, false);
                $PushOptions = unserialize($PushOptions);
                switch ($PushService) {
                    // Case 1 is missing because NMA is dead.
                    case '2':
                        $Service = "Prowl";
                        break;
                    // Case 3 is missing because notifo is dead.
                    case '4':
                        $Service = "Toasty";
                        break;
                    case '5':
                        $Service = "Pushover";
                        break;
                    case '6':
                        $Service = "PushBullet";
                        break;
                    default:
                        break;
                }
                if (!empty($Service) && !empty($PushOptions['PushKey'])) {
                    $Options = [
                        "service" => strtolower($Service),
                        "user" => ["key" => $PushOptions['PushKey']],
                        "message" => ["title" => $Title, "body" => $Body, "url" => $URL]
                    ];

                    if ($Service === 'PushBullet') {
                        $Options["user"]["device"] = $PushOptions['PushDevice'];
                    }

                    self::$db->prepared_query("
                        INSERT INTO push_notifications_usage
                               (PushService, TimesUsed)
                        VALUES (?,           1)
                        ON DUPLICATE KEY UPDATE
                            TimesUsed = TimesUsed + 1
                        ", $Service
                    );
                    $PushServerSocket = fsockopen("127.0.0.1", 6789);
                    fwrite($PushServerSocket, json_encode($Options));
                    fclose($PushServerSocket);
                }
            }
            self::$db->set_query_id($QueryID);
        }
    }

    /**
     * Gets users who have push notifications enabled
     */
    public function pushableUsers(int $userId) {
        self::$db->prepared_query("
            SELECT UserID
            FROM users_push_notifications
            WHERE PushService != 0
                AND UserID != ?
            ", $userId
        );
        return self::$db->collect("UserID", false);
    }
}
