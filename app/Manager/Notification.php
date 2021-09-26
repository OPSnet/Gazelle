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
        'info' => self::INFO,
    ];

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
    const REQUESTALERTS = 'RequestAlerts';
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
        'RequestAlerts',
    ];

    protected $user;
    protected $userId;
    protected $subscription;
    protected $notifications;
    protected $settings;
    protected $skipped;
    protected $typeList = [];

    protected static $registry = [];

    public function __construct(int $userId = null, array $skip = [], bool $load = true, bool $autoSkip = true) {
        // TODO: fix this $skip/$autoSkip insanity
        parent::__construct();
        if ($userId) {
            $this->load($userId, $skip, $load, $autoSkip);
        }
    }

    protected function load(int $userId, array $skip, bool $load, bool $autoSkip) {
        $this->userId = $userId;
        $this->user = new \Gazelle\User($userId);
        $this->notifications = [];
        $this->settings = $this->settings();
        $this->skipped = $skip;
        $this->subscription = new Subscription($this->userId);
        if ($autoSkip) {
            foreach ($this->settings as $key => $value) {
                // Skip disabled and traditional settings
                if ($value == self::OPT_DISABLED || $this->isTraditional($key)) {
                    $this->skipped[$key] = true;
                }
            }
        }
        if ($load) {
            $this->loadGlobal();
            if (!$this->isSkipped(self::BLOG)) {
                $this->loadBlog();
            }
            if (!$this->isSkipped(self::COLLAGES)) {
                $this->loadCollages();
            }
            if (!$this->isSkipped(self::STAFFBLOG)) {
                $this->loadStaffBlog();
            }
            if (!$this->isSkipped(self::STAFFPM)) {
                $this->loadStaffPMs();
            }
            if (!$this->isSkipped(self::INBOX)) {
                $this->loadInbox();
            }
            if (!$this->isSkipped(self::NEWS)) {
                $this->loadNews();
            }
            if (!$this->isSkipped(self::TORRENTS)) {
                $this->loadTorrents();
            }
            if (!$this->isSkipped(self::QUOTES)) {
                $this->loadQuotes();
            }
            if (!$this->isSkipped(self::SUBSCRIPTIONS)) {
                $this->loadSubscriptions();
            }
        }
    }

    public function setType(string $type) {
        $this->typeList[] = $type;
        return $this;
    }

    public function isTraditional(string $type): bool {
        return in_array($this->settings[$type], [self::OPT_TRADITIONAL, self::OPT_TRADITIONAL_PUSH]);
    }

    public function isSkipped(string $type): bool {
        return isset($this->skipped[$type]);
    }

    public function useNoty(): bool {
        return in_array(self::OPT_POPUP, $this->settings) || in_array(self::OPT_POPUP_PUSH, $this->settings);
    }

    public function notifications() {
        if ($this->typeList) {
            // If we are coming from the Ajax endpoint, it is possible to
            // specify only the notifications you want, however, as soon as
            // an Notification object is built, all the notification types
            // are loaded. This ugly hack is here to remove them after the
            // fact to satisfy the Ajax call.
            $typeList = array_keys($this->notifications);
            foreach ($typeList as $type) {
                if (!in_array($type, $this->typeList)) {
                    unset($this->notifications[$type]);
                }
            }
        }
        return $this->notifications;
    }

    public function clear() {
        $this->notifications = [];
    }

    protected function create(string $type, string $message, string $url, string $importance, int $id = 0) {
        $this->notifications[$type] = [
            'id'         => $id,
            'importance' => $importance,
            'message'    => $message,
            'url'        => $url,
        ];
        if (!$this->useNoty()) {
            return;
        }
        // This is needed for Noty notifications in the privatefooter
        // There has to be a better way
        if (!isset(self::$registry[$this->userId])) {
            self::$registry[$this->userId] = [];
        }
        self::$registry[$this->userId][$type] = [
            'id'         => $id,
            'importance' => $importance,
            'message'    => $message,
            'url'        => $url,
        ];
    }

    public function registeredNotifications(int $userId): array {
        return self::$registry[$userId] ?? [];
    }

    public function notifyUser($userId, $type, $message, $url, $importance = self::INFO) {
        $this->notifyUsers([$userId], $type, $message, $url, $importance);
    }

    public function notifyUsers($userIds, $type, $message, $url, $importance = self::INFO) {
        /**
        if (!isset($importance)) {
            $importance = self::INFO;
        }
        $type = db_string($type);
        if (!empty($userIds)) {
            $userIds = implode(",", $userIds);
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT UserID
                FROM users_notifications_settings
                WHERE $type != 0
                    AND UserID IN ($userIds)");
            $userIds = array();
            while ([$id] = $this->db->next_record()) {
                $userIds[] = $id;
            }
            $this->db->set_query_id($QueryID);
            foreach ($userIds as $userId) {
                $oneReads = $this->cache->get_value("notifications_one_reads_$userId");
                if (!$oneReads) {
                    $oneReads = array();
                }
                array_unshift($oneReads, $this->create($oneReads, null, $message, $url, $importance, "oneread_" . uniqid()));
                $oneReads = array_filter($oneReads);
                $this->cache->cache_value("notifications_one_reads_$userId", $oneReads, 0);
            }
        }
        **/
    }

    public function clearOneRead($id) {
        $oneReads = $this->cache->get_value('notifications_one_reads_' . $this->userId);
        if ($oneReads) {
            unset($oneReads[$id]);
            if (count($oneReads) > 0) {
                $this->cache->cache_value('notifications_one_reads_' . $this->userId, $oneReads, 0);
            } else {
                $this->cache->delete_value('notifications_one_reads_' . $this->userId);
            }
        }
    }

    protected function loadGlobal() {
        $notification = $this->cache->get_value('global_notification');
        if ($notification) {
            if (!$this->cache->get_value('user_read_global_' . $this->userId)) {
                $this->create(self::GLOBALNOTICE,  $notification['Message'], $notification['URL'], $notification['Importance']);
            }
        }
    }

    public function global() {
        return $this->cache->get_value('global_notification');
    }

    public function setGlobal($message, $url, $importance, $expiration) {
        if (empty($message) || empty($expiration)) {
            error('Error setting notification');
        }
        $this->cache->cache_value('global_notification', ["Message" => $message, "URL" => $url, "Importance" => $importance, "Expiration" => $expiration], $expiration);
    }

    public function deleteGlobal() {
        $this->cache->delete_value('global_notification');
    }

    public function clearGlobal() {
        $global = $this->cache->get_value('global_notification');
        if ($global) {
            // This is some trickery
            // since we can't know which users have the read cache key set
            // we set the expiration time of their cache key to that of the length of the notification
            // this guarantees that their cache key will expire after the notification expires
            $this->cache->cache_value('user_read_global_' . $this->userId, true, $global['Expiration']);
        }
    }

    /* NB: Object-oriented orthodoxy would suggest that the loadFoo() methods
     * below be marked as protected, however, in the absence of anything
     * resembling unit tests, it is easier to leave them public, so that they
     * can be called from Boris.
     */

    public function loadBlog() {
        [$blogId, $title] = (new \Gazelle\Manager\Blog)->latest();
        if ($blogId > (new \Gazelle\WitnessTable\UserReadBlog)->lastRead($this->user->id())) {
            $this->create(self::BLOG, "Blog: $title", "blog.php#blog$blogId", self::IMPORTANT, $blogId);
        }
    }

    public function loadCollages() {
        if (!check_perms('site_collages_subscribe')) {
            return;
        }
        $new = $this->user->collageUnreadCount();
        if ($new > 0) {
            $this->create(self::COLLAGES, 'You have ' . article($new) . ' new collage update' . plural($new),
                'userhistory.php?action=subscribed_collages', self::INFO);
        }
    }

    public function catchupCollage(int $collageId) {
        $this->db->prepared_query("
            UPDATE users_collage_subs SET
                LastVisit = now()
            WHERE UserID = ? AND CollageID = ?
            ", $this->userId, $collageId
        );
        $this->cache->delete_value(sprintf(\Gazelle\Collage::SUBS_NEW_KEY, $this->userId));
    }

    public function catchupAllCollages() {
        $this->db->prepared_query("
            UPDATE users_collage_subs SET
                LastVisit = now()
            WHERE UserID = ?
            ", $this->userId
        );
        $this->cache->delete_value(sprintf(\Gazelle\Collage::SUBS_NEW_KEY, $this->userId));
    }

    public function loadInbox() {
        $count = $this->user->inboxUnreadCount();
        if ($count > 0) {
            $this->create(self::INBOX, 'You have ' . article($count) . ' new message' . plural($count), "inbox.php", self::INFO);
        }
    }

    public function inboxAlert() {
        $this->loadInbox();
        $new = $this->notifications();
        $alert = isset($new[self::INBOX])
            ? sprintf('<a href="%s">%s</a>', $new[self::INBOX]['url'], $new[self::INBOX]['message'])
            : null;
        $this->clear();
        return $alert;
    }

    public function torrentAlert() {
        $this->loadTorrents();
        $new = $this->notifications();
        $alert = isset($new[self::TORRENTS])
            ? sprintf('<a href="%s">%s</a>', $new[self::TORRENTS]['url'], $new[self::TORRENTS]['message'])
            : null;
        $this->clear();
        return $alert;
    }

    public function loadNews() {
        [$newsId, $title] = (new \Gazelle\Manager\News)->latest();
        if ($newsId > (new \Gazelle\WitnessTable\UserReadNews)->lastRead($this->user->id())) {
            $this->create(self::NEWS, "Announcement: $title", "index.php#news$newsId", self::IMPORTANT, $newsId);
        }
    }

    public function clearNews() {
        if ((new \Gazelle\WitnessTable\UserReadNews)->witness($this->userId)) {
            $this->cache->deleteMulti(['u_' . $this->userId, 'user_info_heavy_' . $this->userId]);
        }
        return (new \Gazelle\Manager\News)->latestId();
    }

    public function loadQuotes() {
        if ($this->user->info()['NotifyOnQuote']) {
            $count = $this->subscription->unreadQuotes();
            if ($count > 0) {
                $this->create(self::QUOTES, 'New quote' . plural($count), 'userhistory.php?action=quote_notifications', self::INFO);
            }
        }
    }

    public function loadStaffBlog() {
        if (!check_perms('users_mod')) {
            return;
        }
        if (($readTime = $this->cache->get_value('staff_blog_read_' . $this->userId)) === false) {
            $readTime = $this->db->scalar("
                SELECT unix_timestamp(Time)
                FROM staff_blog_visits
                WHERE UserID = ?
                ", $this->userId
            );
            if (!$readTime) {
                $readTime = 0;
            }
            $this->cache->cache_value('staff_blog_read_' . $this->userId, $readTime, 0);
        }
        if (($latestTime = $this->cache->get_value('staff_blog_latest_time')) === false) {
            $latestTime = $this->db->scalar("
                SELECT unix_timestamp(max(Time)) FROM staff_blog
            ");
            if (!$latestTime) {
                $latestTime = 0;
            }
            $this->cache->cache_value('staff_blog_latest_time', $latestTime, 0);
        }
        if ($readTime < $latestTime) {
            $this->create(self::STAFFBLOG, 'New Staff Blog Post!', 'staffblog.php', self::IMPORTANT);
        }
    }

    public function loadStaffPMs() {
        if (($new = $this->cache->get_value('staff_pm_new_' . $this->userId)) === false) {
            $new = $this->db->scalar("
                SELECT count(*)
                FROM staff_pm_conversations
                WHERE Unread = '1'
                    AND UserID = ?
                ", $this->userId
            );
            $this->cache->cache_value('staff_pm_new_' . $this->userId, $new, 0);
        }
        if ($new > 0) {
            $this->create(self::STAFFPM, 'You have ' . article($new) . ' new Staff PM' . plural($new), 'staffpm.php', self::INFO);
        }
    }

    public function loadSubscriptions() {
        $unread = $this->subscription->unread();
        if ($unread > 0) {
            $this->create(self::SUBSCRIPTIONS, 'New subscription' . plural($unread), 'userhistory.php?action=subscriptions', self::INFO);
        }
    }

    public function clearSubscriptions() {
        $this->subscription->clear();
    }

    public function loadTorrents() {
        if (!check_perms('site_torrents_notify')) {
            return;
        }
        $new = $this->user->unreadTorrentNotifications();
        if ($new > 0) {
            $this->create(self::TORRENTS, 'You have ' . article($new) . ' new torrent notification' . plural($new), 'torrents.php?action=notify', self::INFO);
        }
    }

    public function settings() {
        if (($settings = $this->cache->get_value("users_notifications_settings_" . $this->userId)) === false) {
            $this->db->prepared_query("
                SELECT *
                FROM users_notifications_settings AS n
                LEFT JOIN users_push_notifications AS p USING (UserID)
                WHERE n.UserID = ?
                ", $this->userId
            );
            $settings = $this->db->next_record(MYSQLI_ASSOC, false);
            $this->cache->cache_value("users_notifications_settings_" . $this->userId, $settings, 86400);
        }
        return $settings;
    }

    public function save(array $settings, array $options, int $service, $device) {
        $set = [];
        $args = [];
        foreach (self::$Types as $Type) {
            $Popup = array_key_exists("notifications_{$Type}_popup", $settings);
            $Traditional = array_key_exists("notifications_{$Type}_traditional", $settings);
            $Push = array_key_exists("notifications_{$Type}_push", $settings);
            if ($Push) {
                if ($Popup) {
                    $Result = self::OPT_POPUP_PUSH;
                } elseif ($Traditional) {
                    $Result = self::OPT_TRADITIONAL_PUSH;
                } else {
                    $Result = self::OPT_PUSH;
                }
            } elseif ($Traditional) {
                $Result = self::OPT_TRADITIONAL;
            } elseif ($Popup) {
                $Result = self::OPT_POPUP;
            } else {
                $Result = self::OPT_DISABLED;
            }
            $set[] = "$Type = ?";
            $args[] = $Result;
        }
        $set = implode(",", $set);
        $args[] = $this->userId;

        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            UPDATE users_notifications_settings SET
                $set
            WHERE UserID = ?
            ", ... $args
        );

        if (!$service) {
            $this->db->prepared_query("
                UPDATE users_push_notifications SET PushService = 0 WHERE UserID = ?
                ", $this->userId
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
                ", $this->userId, $service, $options,
                    $service, $options
            );
        }
        $this->db->set_query_id($QueryID);
        $this->cache->delete_value("users_notifications_settings_{$this->userId}");
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
            $this->db->prepared_query($SQL);

            if ($this->db->has_results()) {
                [$PushService, $PushOptions] = $this->db->next_record(MYSQLI_NUM, false);
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

                    $this->db->prepared_query("
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
            $this->db->set_query_id($QueryID);
        }
    }

    /**
     * Gets users who have push notifications enabled
     */
    public function pushableUsers() {
        $this->db->prepared_query("
            SELECT UserID
            FROM users_push_notifications
            WHERE PushService != 0
                AND UserID != ?
            ", $this->userId
        );
        return $this->db->collect("UserID");
    }
}
