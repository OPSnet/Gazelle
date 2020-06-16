<?php

namespace Gazelle\Manager;

use \Gazelle\Inbox;

class Notification extends \Gazelle\Base {
    // Option types
    const OPT_DISABLED = 0;
    const OPT_POPUP = 1;
    const OPT_TRADITIONAL = 2;
    const OPT_PUSH = 3;
    const OPT_POPUP_PUSH = 4;
    const OPT_TRADITIONAL_PUSH = 5;

    // Importances
    const IMPORTANT = 'information';
    const CRITICAL = 'error';
    const WARNING = 'warning';
    const INFO = 'confirmation';

    // TODO: methodize
    public static $Importances = [
        'important' => self::IMPORTANT,
        'critical' => self::CRITICAL,
        'warning' => self::WARNING,
        'info' => self::INFO];

    // Types. These names must correspond to column names in users_notifications_settings
    const NEWS = 'News';
    const BLOG = 'Blog';
    const STAFFBLOG = 'StaffBlog';
    const STAFFPM = 'StaffPM';
    const INBOX = 'Inbox';
    const QUOTES = 'Quotes';
    const SUBSCRIPTIONS = 'Subscriptions';
    const TORRENTS = 'Torrents';
    const COLLAGES = 'Collages';
    const SITEALERTS = 'SiteAlerts';
    const FORUMALERTS = 'ForumAlerts';
    const REQUESTALERTS = 'RequestAlerts';
    const COLLAGEALERTS = 'CollageAlerts';
    const TORRENTALERTS = 'TorrentAlerts';
    const GLOBALNOTICE = 'Global';

    // TODO: methodize
    public static $Types = [
        'News',
        'Blog',
        'StaffPM',
        'Inbox',
        'Quotes',
        'Subscriptions',
        'Torrents',
        'Collages',
        'SiteAlerts',
        'ForumAlerts',
        'RequestAlerts',
        'CollageAlerts',
        'TorrentAlerts'];

    protected $UserID;
    protected $userInfo;
    protected $subscription;
    protected $Notifications;
    protected $Settings;
    protected $Skipped;

    public function __construct($UserID = null, $Skip = [], $Load = true, $AutoSkip = true) {
        parent::__construct();
        if ($UserID) {
            $this->load($UserID, $Skip, $Load, $AutoSkip);
        }
    }

    protected function load($UserID, $Skip, $Load, $AutoSkip) {
        $this->UserID = $UserID;
        $this->Notifications = [];
        $this->Settings = $this->settings();
        $this->Skipped = $Skip;
        $this->userInfo = \Users::user_heavy_info($this->UserID);
        $this->subscription = new Subscription($this->UserID);
        if ($AutoSkip) {
            foreach ($this->Settings as $Key => $Value) {
                // Skip disabled and traditional settings
                if ($Value == self::OPT_DISABLED || $this->isTraditional($Key)) {
                    $this->Skipped[$Key] = true;
                }
            }
        }
        if ($Load) {
            $this->loadGlobal();
            if (!isset($this->Skipped[self::BLOG])) {
                $this->loadBlog();
            }
            if (!isset($this->Skipped[self::COLLAGES])) {
                $this->loadCollages();
            }
            if (!isset($this->Skipped[self::STAFFPM])) {
                $this->loadStaffPMs();
            }
            if (!isset($this->Skipped[self::INBOX])) {
                $this->loadInbox();
            }
            if (!isset($this->Skipped[self::NEWS])) {
                $this->loadNews();
            }
            if (!isset($this->Skipped[self::TORRENTS])) {
                $this->loadTorrents();
            }
            if (!isset($this->Skipped[self::QUOTES])) {
                $this->loadQuotes();
            }
            if (!isset($this->Skipped[self::SUBSCRIPTIONS])) {
                $this->loadSubscriptions();
            }
        }
    }

    public function isTraditional($Type) {
        return $this->Settings[$Type] == self::OPT_TRADITIONAL || $this->Settings[$Type] == self::OPT_TRADITIONAL_PUSH;
    }

    public function isSkipped($Type) {
        return isset($this->Skipped[$Type]);
    }

    public function useNoty() {
        return in_array(self::OPT_POPUP, $this->Settings) || in_array(self::OPT_POPUP_PUSH, $this->Settings);
    }

    public function notifications() {
        return $this->Notifications;
    }

    public function clear() {
        $this->Notifications = [];
    }

    protected function create($Type, $ID, $Message, $URL, $Importance) {
        $this->Notifications[$Type] = [
            'id' => (int)$ID,
            'message' => $Message,
            'url' => $URL,
            'importance' => $Importance
        ];
    }

    public function notifyUser($UserID, $Type, $Message, $URL, $Importance = self::INFO) {
        $this->notifyUsers([$UserID], $Type, $Message, $URL, $Importance);
    }

    public function notifyUsers($UserIDs, $Type, $Message, $URL, $Importance = self::INFO) {
        /**
        if (!isset($Importance)) {
            $Importance = self::INFO;
        }
        $Type = db_string($Type);
        if (!empty($UserIDs)) {
            $UserIDs = implode(',', $UserIDs);
            $QueryID = $this->db->get_query_id();
            $this->db->query("
                SELECT UserID
                FROM users_notifications_settings
                WHERE $Type != 0
                    AND UserID IN ($UserIDs)");
            $UserIDs = array();
            while (list($ID) = $this->db->next_record()) {
                $UserIDs[] = $ID;
            }
            $this->db->set_query_id($QueryID);
            foreach ($UserIDs as $UserID) {
                $OneReads = $this->cache->get_value("notifications_one_reads_$UserID");
                if (!$OneReads) {
                    $OneReads = array();
                }
                array_unshift($OneReads, $this->create($OneReads, "oneread_" . uniqid(), null, $Message, $URL, $Importance));
                $OneReads = array_filter($OneReads);
                $this->cache->cache_value("notifications_one_reads_$UserID", $OneReads, 0);
            }
        }
        **/
    }

    public function clearOneRead($ID) {
        $OneReads = $this->cache->get_value('notifications_one_reads_' . $this->UserID);
        if ($OneReads) {
            unset($OneReads[$ID]);
            if (count($OneReads) > 0) {
                $this->cache->cache_value('notifications_one_reads_' . $this->UserID, $OneReads, 0);
            } else {
                $this->cache->delete_value('notifications_one_reads_' . $this->UserID);
            }
        }

    }

    protected function loadGlobal() {
        $GlobalNotification = $this->cache->get_value('global_notification');
        if ($GlobalNotification) {
            $Read = $this->cache->get_value('user_read_global_' . $this->UserID);
            if (!$Read) {
                $this->create(self::GLOBALNOTICE, 0,  $GlobalNotification['Message'], $GlobalNotification['URL'], $GlobalNotification['Importance']);
            }
        }
    }

    public function global() {
        return $this->cache->get_value('global_notification');
    }

    public function setGlobal($Message, $URL, $Importance, $Expiration) {
        if (empty($Message) || empty($Expiration)) {
            error('Error setting notification');
        }
        $this->cache->cache_value('global_notification', ["Message" => $Message, "URL" => $URL, "Importance" => $Importance, "Expiration" => $Expiration], $Expiration);
    }

    public function deleteGlobal() {
        $this->cache->delete_value('global_notification');
    }

    public function clearGlobal() {
        $GlobalNotification = $this->cache->get_value('global_notification');
        if ($GlobalNotification) {
            // This is some trickery
            // since we can't know which users have the read cache key set
            // we set the expiration time of their cache key to that of the length of the notification
            // this guarantees that their cache key will expire after the notification expires
            $this->cache->cache_value('user_read_global_' . $this->UserID, true, $GlobalNotification['Expiration']);
        }
    }

    /* NB: Object-oriented orthodoxy would suggest that the loadFoo() methods
     * below be marked as protected, however, in the absence of anything
     * resembling unit tests, it is easier to leave them public, so that they
     * can be called from Boris.
     */

    public function loadBlog() {
        $MyBlog = $this->userInfo['LastReadBlog'];
        $CurrentBlog = $this->cache->get_value('blog_latest_id');
        $Title = $this->cache->get_value('blog_latest_title');
        if ($CurrentBlog === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->query('
                SELECT ID, Title
                FROM blog
                WHERE Important = 1
                ORDER BY Time DESC
                LIMIT 1');
            if ($this->db->has_results()) {
                list($CurrentBlog, $Title) = $this->db->next_record();
            } else {
                $CurrentBlog = -1;
            }
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value('blog_latest_id', $CurrentBlog, 0);
            $this->cache->cache_value('blog_latest_title', $Title, 0);
        }
        if ($MyBlog < $CurrentBlog) {
            $this->create(self::BLOG, $CurrentBlog, "Blog: $Title", "blog.php#blog$CurrentBlog", self::IMPORTANT);
        }
    }

    public function clearBlog($Blog = null) {
        $MyBlog = $this->userInfo['LastReadBlog'];
        $QueryID = $this->db->get_query_id();
        if (!$Blog) {
            if (!$Blog = $this->cache->get_value('blog')) {
                $this->db->query("
                    SELECT
                        b.ID,
                        um.Username,
                        b.UserID,
                        b.Title,
                        b.Body,
                        b.Time,
                        b.ThreadID
                    FROM blog AS b
                        LEFT JOIN users_main AS um ON b.UserID = um.ID
                    ORDER BY Time DESC
                    LIMIT 1");
                $Blog = $this->db->to_array();
            }
        }
        if ($MyBlog < $Blog[0][0]) {
            $this->cache->begin_transaction('user_info_heavy_' . $this->UserID);
            $this->cache->update_row(false, ['LastReadBlog' => $Blog[0][0]]);
            $this->cache->commit_transaction(0);
            $this->db->query("
                UPDATE users_info
                SET LastReadBlog = '". $Blog[0][0]."'
                WHERE UserID = " . $this->UserID);
        }
        $this->db->set_query_id($QueryID);
        return $Blog[0][0];
    }

    public function loadCollages() {
        if (check_perms('site_collages_subscribe')) {
            $NewCollages = $this->cache->get_value('collage_subs_user_new_' . $this->UserID);
            if ($NewCollages === false) {
                    $QueryID = $this->db->get_query_id();
                    $this->db->query("
                        SELECT COUNT(DISTINCT s.CollageID)
                        FROM users_collage_subs AS s
                            JOIN collages AS c ON s.CollageID = c.ID
                            JOIN collages_torrents AS ct ON ct.CollageID = c.ID
                        WHERE s.UserID = " . $this->UserID . "
                            AND ct.AddedOn > s.LastVisit
                            AND c.Deleted = '0'");
                    list($NewCollages) = $this->db->next_record();
                    $this->db->set_query_id($QueryID);
                    $this->cache->cache_value('collage_subs_user_new_' . $this->UserID, $NewCollages, 0);
            }
            if ($NewCollages > 0) {
                $Title = 'You have ' . ($NewCollages == 1 ? 'a' : $NewCollages) . ' new collage update' . ($NewCollages > 1 ? 's' : '');
                $this->create(self::COLLAGES, 0, $Title, 'userhistory.php?action=subscribed_collages', self::INFO);
            }
        }
    }

    public function clearCollages() {
        $QueryID = $this->db->get_query_id();
        $this->db->query("
            UPDATE users_collage_subs
            SET LastVisit = NOW()
            WHERE UserID = " . $this->UserID);
        $this->cache->delete_value('collage_subs_user_new_' . $this->UserID);
        $this->db->set_query_id($QueryID);
    }

    public function loadInbox() {
        $NewMessages = $this->cache->get_value('inbox_new_' . $this->UserID);
        if ($NewMessages === false) {
            $NewMessages = $this->db->scalar("
                SELECT count(*)
                FROM pm_conversations_users
                WHERE UnRead    = '1'
                    AND InInbox = '1'
                    AND UserID  = ?
                ", $this->UserID
            );
            $this->cache->cache_value('inbox_new_' . $this->UserID, $NewMessages, 0);
        }
        if ($NewMessages > 0) {
            $Title = 'You have ' . ($NewMessages == 1 ? 'a' : $NewMessages) . ' new message' . ($NewMessages > 1 ? 's' : '');
            $this->create(self::INBOX, 0, $Title, Inbox::getLinkQuick('inbox', $this->userInfo['ListUnreadPMsFirst'] ?? false), self::INFO);
        }
    }

    public function clearInbox() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE pm_conversations_users
            SET Unread = '0'
            WHERE Unread = '1'
                AND UserID = ?
            ", $this->UserID
        );
        $this->cache->delete_value('inbox_new_' . $this->UserID);
        $this->db->set_query_id($QueryID);
    }

    public function loadNews() {
        $MyNews = $this->userInfo['LastReadNews'];
        $CurrentNews = $this->cache->get_value('news_latest_id');
        $Title = $this->cache->get_value('news_latest_title');
        if ($CurrentNews === false || $Title === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->query('
                SELECT ID, Title
                FROM news
                ORDER BY Time DESC
                LIMIT 1');
            if ($this->db->has_results()) {
                list($CurrentNews, $Title) = $this->db->next_record();
            } else {
                $CurrentNews = -1;
            }
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value('news_latest_id', $CurrentNews, 0);
            $this->cache->cache_value('news_latest_title', $Title, 0);
        }
        if ($MyNews < $CurrentNews) {
            $this->create(self::NEWS, $CurrentNews, "Announcement: $Title", "index.php#news$CurrentNews", self::IMPORTANT);
        }
    }

    public function clearNews($News = null) {
        $QueryID = $this->db->get_query_id();
        if (!$News) {
            if (!$News = $this->cache->get_value('news')) {
                $this->db->query('
                    SELECT
                        ID,
                        Title,
                        Body,
                        Time
                    FROM news
                    ORDER BY Time DESC
                    LIMIT 1');
                $News = $this->db->to_array(false, MYSQLI_NUM, false);
                $this->cache->cache_value('news_latest_id', $News[0][0], 0);
            }
        }
        if ($this->userInfo['LastReadNews'] != $News[0][0]) {
            $this->cache->begin_transaction('user_info_heavy_' . $this->UserID);
            $this->cache->update_row(false, ['LastReadNews' => $News[0][0]]);
            $this->cache->commit_transaction(0);
            $this->db->query("
                UPDATE users_info
                SET LastReadNews = '".$News[0][0]."'
                WHERE UserID = " . $this->UserID);
        }
        $this->db->set_query_id($QueryID);
        return $News[0][0];
    }

    public function loadQuotes() {
        if (isset($this->userInfo['NotifyOnQuote']) && $this->userInfo['NotifyOnQuote']) {
            $QuoteNotificationsCount = $this->subscription->unreadQuotes();
            if ($QuoteNotificationsCount > 0) {
                $Title = 'New quote' . ($QuoteNotificationsCount > 1 ? 's' : '');
                $this->create(self::QUOTES, 0, $Title, 'userhistory.php?action=quote_notifications', self::INFO);
            }
        }
    }

    public function clearQuotes() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE users_notify_quoted
            SET UnRead = '0'
            WHERE UserID = ?
            ", $this->UserID
        );
        $this->cache->delete_value('notify_quoted_' . $this->UserID);
        $this->db->set_query_id($QueryID);
    }

    public function loadStaffBlog() {
        if (check_perms('users_mod')) {
            if (($SBlogReadTime = $this->cache->get_value('staff_blog_read_' . $this->UserID)) === false) {
                $QueryID = $this->db->get_query_id();
                $this->db->query("
                    SELECT Time
                    FROM staff_blog_visits
                    WHERE UserID = " . $this->UserID);
                if (list($SBlogReadTime) = $this->db->next_record()) {
                    $SBlogReadTime = strtotime($SBlogReadTime);
                } else {
                    $SBlogReadTime = 0;
                }
                $this->db->set_query_id($QueryID);
                $this->cache->cache_value('staff_blog_read_' . $this->UserID, $SBlogReadTime, 1209600);
            }
            if (($LatestSBlogTime = $this->cache->get_value('staff_blog_latest_time')) === false) {
                $QueryID = $this->db->get_query_id();
                $this->db->query('
                    SELECT MAX(Time)
                    FROM staff_blog');
                if (list($LatestSBlogTime) = $this->db->next_record()) {
                    $LatestSBlogTime = strtotime($LatestSBlogTime);
                } else {
                    $LatestSBlogTime = 0;
                }
                $this->db->set_query_id($QueryID);
                $this->cache->cache_value('staff_blog_latest_time', $LatestSBlogTime, 1209600);
            }
            if ($SBlogReadTime < $LatestSBlogTime) {
                $this->create(self::STAFFBLOG, 0, 'New Staff Blog Post!', 'staffblog.php', self::IMPORTANT);
            }
        }
    }

    public function loadStaffPMs() {
        $NewStaffPMs = $this->cache->get_value('staff_pm_new_' . $this->UserID);
        if ($NewStaffPMs === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->query("
                SELECT COUNT(ID)
                FROM staff_pm_conversations
                WHERE UserID = '" . $this->UserID . "'
                    AND Unread = '1'");
            list($NewStaffPMs) = $this->db->next_record();
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value('staff_pm_new_' . $this->UserID, $NewStaffPMs, 0);
        }
        if ($NewStaffPMs > 0) {
            $Title = 'You have ' . ($NewStaffPMs == 1 ? 'a' : $NewStaffPMs) . ' new Staff PM' . ($NewStaffPMs > 1 ? 's' : '');
            $this->create(self::STAFFPM, 0, $Title, 'staffpm.php', self::INFO);
        }
    }

    public function clearStaffPMs() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = false
            WHERE Unread = true
                AND UserID = ?
            ", $this->UserID
        );
        $this->cache->delete_value('staff_pm_new_' . $this->UserID);
        $this->db->set_query_id($QueryID);
    }

    public function loadSubscriptions() {
        $SubscriptionsCount = $this->subscription->unread();
        if ($SubscriptionsCount > 0) {
            $Title = 'New subscription' . ($SubscriptionsCount > 1 ? 's' : '');
            $this->create(self::SUBSCRIPTIONS, 0, $Title, 'userhistory.php?action=subscriptions', self::INFO);
        }
    }

    public function clearSubscriptions() {
        $this->subscription->clear();
    }

    public function loadTorrents() {
        if (!check_perms('site_torrents_notify')) {
            $NewNotifications = 0;
        }
        else {
            $NewNotifications = $this->cache->get_value('notifications_new_' . $this->UserID);
            if ($NewNotifications === false) {
                $QueryID = $this->db->get_query_id();
                $this->db->query("
                    SELECT COUNT(UserID)
                    FROM users_notify_torrents
                    WHERE UserID = ' " . $this->UserID . "'
                        AND UnRead = '1'");
                list($NewNotifications) = $this->db->next_record();
                $this->db->set_query_id($QueryID);
                $this->cache->cache_value('notifications_new_' . $this->UserID, $NewNotifications, 0);
            }
        }
        if ($NewNotifications > 0) {
            $Title = 'You have ' . ($NewNotifications == 1 ? 'a' : $NewNotifications) . ' new torrent notification' . ($NewNotifications > 1 ? 's' : '');
            $this->create(self::TORRENTS, 0, $Title, 'torrents.php?action=notify', self::INFO);
        }
    }

    public function clearTorrents() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE users_notify_torrents
            SET Unread = '0'
            WHERE UnRead = '1'
                AND UserID = ?
            ", $this->UserID
        );
        $this->cache->delete_value('notifications_new_' . $this->UserID);
        $this->db->set_query_id($QueryID);
    }

    public function settings() {
        if (($Results = $this->cache->get_value("users_notifications_settings_" . $this->UserID)) === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT *
                FROM users_notifications_settings AS n
                LEFT JOIN users_push_notifications AS p USING (UserID)
                WHERE n.UserID = ?
                ", $this->UserID
            );
            $Results = $this->db->next_record(MYSQLI_ASSOC, false);
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value("users_notifications_settings_" . $this->UserID, $Results, 86400);
        }
        return $Results;
    }

    public function save(array $Settings, array $options, int $service, $device) {
        $Update = [];
        foreach (self::$Types as $Type) {
            $Popup = array_key_exists("notifications_{$Type}_popup", $Settings);
            $Traditional = array_key_exists("notifications_{$Type}_traditional", $Settings);
            $Push = array_key_exists("notifications_{$Type}_push", $Settings);
            $Result = self::OPT_DISABLED;
            if ($Popup) {
                $Result = $Push ? self::OPT_POPUP_PUSH : self::OPT_POPUP;
            } elseif ($Traditional) {
                $Result = $Push ? self::OPT_TRADITIONAL_PUSH : self::OPT_TRADITIONAL;
            } elseif ($Push) {
                $Result = self::OPT_PUSH;
            }
            $Update[] = "$Type = $Result";
        }
        $Update = implode(',', $Update);

        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE users_notifications_settings
            SET $Update
            WHERE UserID = ?
            ", $this->UserID
        );

        if (!$service) {
            $this->db->prepared_query("
                UPDATE users_push_notifications SET PushService = 0 WHERE UserID = ?
                ", $this->UserID
            );
        } else {
            if ($service == 6) { //pushbullet
                $options['PushDevice'] = $device;
            }
            $options = serialize($options);
            $this->db->prepared_query("
                INSERT INTO users_push_notifications
                       (UserID, PushService, PushOptions)
                VALUES (?,      ?,           ?)
                ON DUPLICATE KEY UPDATE
                    PushService = ?,
                    PushOptions = ?
                ", $this->UserID, $service, $options,
                    $service, $options
            );
        }
        $this->db->set_query_id($QueryID);
        $this->cache->delete_value("users_notifications_settings_$UserID");
    }

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
            $QueryID = $this->db->get_query_id();
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
            $this->db->query($SQL);

            if ($this->db->has_results()) {
                list($PushService, $PushOptions) = $this->db->next_record(MYSQLI_NUM, false);
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

                    $this->db->query("
                        INSERT INTO push_notifications_usage
                            (PushService, TimesUsed)
                        VALUES
                            ('$Service', 1)
                        ON DUPLICATE KEY UPDATE
                            TimesUsed = TimesUsed + 1");

                    $PushServerSocket = fsockopen("127.0.0.1", 6789);
                    fwrite($PushServerSocket, json_encode($Options));
                    fclose($PushServerSocket);
                }
            }
            $this->db->set_query_id($QueryID);
        }
    }

    /**
     * Gets users who have push notifications enabled
     */
    public function pushableUsers() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            SELECT UserID
            FROM users_push_notifications
            WHERE PushService != 0
                AND UserID != ?
            ", $this->UserID
        );
        $PushUsers = $this->db->collect("UserID");
        $this->db->set_query_id($QueryID);
        return $PushUsers;
    }
}
