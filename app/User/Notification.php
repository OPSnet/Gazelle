<?php

namespace Gazelle\User;

class Notification extends \Gazelle\BaseUser {
    protected const CACHE_KEY = 'u_notif_%d';

    const DISPLAY_DISABLED = 0;
    const DISPLAY_POPUP = 1;
    const DISPLAY_TRADITIONAL = 2;
    const DISPLAY_PUSH = 3;
    const DISPLAY_POPUP_PUSH = 4;
    const DISPLAY_TRADITIONAL_PUSH = 5;

    protected array  $alert;
    protected array  $config;
    protected string $document;
    protected string $action;

    // TODO: methodize
    protected static $Types = [
        'Blog',
        'Collages',
        'Inbox',
        'News',
        'Quotes',
        'StaffPM',
        'Subscriptions',
        'Torrents',
    ];

    public function config() {
        if (isset($this->config)) {
            return $this->config;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id());
        $config = self::$cache->get_value($key);
        $config = false; // TODO allow caching
        if ($config == false) {
            $config = self::$db->rowAssoc("
                SELECT Blog       AS Blog,
                    Collages      AS Collage,
                    Inbox         AS Inbox,
                    News          AS News,
                    Quotes        AS Quote,
                    StaffPM       AS StaffPM,
                    Subscriptions AS Subscription,
                    Torrents      AS Torrent
                FROM users_notifications_settings AS n
                WHERE n.UserID = ?
                ", $this->user->id()
            ) ?? [
                'Blog'          => 0,
                'Collages'      => 0,
                'Inbox'         => 0,
                'News'          => 0,
                'Quotes'        => 0,
                'StaffPM'       => 0,
                'Subscriptions' => 0,
                'Torrents'      => 0,
            ];
            self::$cache->cache_value($key, $config, 0);
        }
        $this->config = $config;
        return $this->config;
    }

    public function setDocument(string $document, string $action): Notification {
        $this->document = $document;
        $this->action   = $action;
        return $this;
    }

    public function alertList(): array {
        if (isset($this->alert)) {
            return $this->alert;
        }
        $config = $this->config();
        $alert = [];
        $noty  = false;
        foreach ($config as $class => $display) {
            if (in_array($display, [self::DISPLAY_POPUP, self::DISPLAY_POPUP_PUSH])) {
                $noty = true;
            }
            // does the user want to see this alert?
            if ($display) {
                $className = "\\Gazelle\\User\\Notification\\$class";
                // are we on the page of the alert?
                if (isset($this->document)) {
                    if ($class === 'Collage' && $this->document === 'userhistory' && $this->action === 'subscribed_collages') {
                        continue;
                    }
                    if ($class === 'Inbox' && $this->document === 'inbox') {
                        continue;
                    }
                    if ($class === 'Quote' && $this->document === 'userhistory' && $this->action === 'quote_notifications') {
                        continue;
                    }
                    if ($class === 'Subscription' && $this->document === 'userhistory' && $this->action === 'subscriptions') {
                        continue;
                    }
                    if ($class === 'Torrent' && $this->document === 'torrents' && $this->action === 'notify') {
                        continue;
                    }
                }
                $notification = new $className($this->user);
                if ($notification->load()) {
                    $alert[$class] = $notification->setDisplay($display);
                }
            }
        }
        $global = new Notification\GlobalNotification($this->user);
        if ($global->load()) {
            $alert['Global'] = $global->setDisplay($noty ? self::DISPLAY_POPUP : DISPLAY_TRADITIONAL);
        }
        $this->alert = $alert;
        return $this->alert;
    }

    public function useNoty(): bool {
        return (bool)count(array_filter($this->alertList(),
            fn ($a) => in_array($a->display(), [self::DISPLAY_POPUP, self::DISPLAY_POPUP_PUSH])));
    }

    public function save(array $settings, array $options, int $service, $device): int {
        $set = [];
        $args = [];
        foreach (self::$Types as $Type) {
            $Popup = array_key_exists("notifications_{$Type}_popup", $settings);
            $Traditional = array_key_exists("notifications_{$Type}_traditional", $settings);
            $Push = array_key_exists("notifications_{$Type}_push", $settings);
            if ($Push) {
                if ($Popup) {
                    $Result = self::DISPLAY_POPUP_PUSH;
                } elseif ($Traditional) {
                    $Result = self::DISPLAY_TRADITIONAL_PUSH;
                } else {
                    $Result = self::DISPLAY_PUSH;
                }
            } elseif ($Traditional) {
                $Result = self::DISPLAY_TRADITIONAL;
            } elseif ($Popup) {
                $Result = self::DISPLAY_POPUP;
            } else {
                $Result = self::DISPLAY_DISABLED;
            }
            $set[] = "$Type = ?";
            $args[] = $Result;
        }
        $set = implode(",", $set);
        $args[] = $this->user->id();

        self::$db->prepared_query("
            UPDATE users_notifications_settings SET
                $set
            WHERE UserID = ?
            ", ...$args
        );
        $affected = self::$db->affected_rows();

        if (!$service) {
            self::$db->prepared_query("
                UPDATE users_push_notifications SET PushService = 0 WHERE UserID = ?
                ", $this->user->id()
            );
        } else {
            if ($service == 6) { //pushbullet
                $options['PushDevice'] = $device;
            }
            $options = serialize($options);
            self::$db->prepared_query("
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
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->user->id()));
        return $affected;
    }

}
