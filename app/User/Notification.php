<?php

namespace Gazelle\User;

class Notification extends \Gazelle\BaseUser {
    final const tableName     = 'users_notifications_settings';
    protected const CACHE_KEY = 'u_notif_%d';

    final const DISPLAY_DISABLED = 0;
    final const DISPLAY_POPUP = 1;
    final const DISPLAY_TRADITIONAL = 2;
    final const DISPLAY_PUSH = 3;
    final const DISPLAY_POPUP_PUSH = 4;
    final const DISPLAY_TRADITIONAL_PUSH = 5;

    protected array  $alert;
    protected array  $config;
    protected string $document;
    protected string $action;

    // for results page
    protected bool $dirty = true;
    protected bool $orderByYear = false;
    protected string $baseQuery;
    protected array $cond = [];
    protected array $args = [];

    // TODO: methodize
    protected static array $Types = [
        'Blog',
        'Collages',
        'Inbox',
        'News',
        'Quotes',
        'StaffPM',
        'Subscriptions',
        'Torrents',
    ];

    public function flush(): static { $this->user()->flush(); return $this; }

    public function config(): array {
        if (isset($this->config)) {
            return $this->config;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id());
        $config = self::$cache->get_value($key);
        // TODO allow caching
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
        $config = array_map('intval', $config);
        self::$cache->cache_value($key, $config, 0);
        $this->config = $config;
        return $this->config;
    }

    public function isActive(string $alertType): bool {
        return $this->config()[$alertType] != self::DISPLAY_DISABLED;
    }

    public function setDocument(string $document, string $action): static {
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
                $className = "\\Gazelle\\User\\Notification\\$class";
                $notification = new $className($this->user);
                if ($notification->load()) { /** @phpstan-ignore-line */
                    $alert[$class] = $notification->setDisplay($display); /** @phpstan-ignore-line */
                }
            }
        }
        $global = new Notification\GlobalNotification($this->user);
        if ($global->load()) {
            // $alert['Global'] = $global->setDisplay($noty ? self::DISPLAY_POPUP : self::DISPLAY_TRADITIONAL);
            $alert['Global'] = $global;
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
        $rename = [
            'Collages'      => 'Collage',
            'Quotes'        => 'Quote',
            'Subscriptions' => 'Subscription',
            'Torrents'      => 'Torrent',
        ];
        foreach (self::$Types as $column) {
            $set[] = "$column = ?";
            $name  = $rename[$column] ?? $column;
            $popup = ($settings[$name] ?? '') === 'popup';
            $trad  = ($settings[$name] ?? '') === 'traditional';
            if (($settings[$name] ?? '') === 'push') {
                if ($popup) {
                    $args[] = self::DISPLAY_POPUP_PUSH;
                } elseif ($trad) {
                    $args[] = self::DISPLAY_TRADITIONAL_PUSH;
                } else {
                    $args[] = self::DISPLAY_PUSH;
                }
            } elseif ($trad) {
                $args[] = self::DISPLAY_TRADITIONAL;
            } elseif ($popup) {
                $args[] = self::DISPLAY_POPUP;
            } else {
                $args[] = self::DISPLAY_DISABLED;
            }
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
                ", $this->user->id(), $service, $options,
                    $service, $options
            );
        }
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->user->id()));
        return $affected;
    }

    protected function valueToArray(?string $value): array {
        if (is_null($value) || in_array(trim($value), ['', '||'])) {
            return [];
        }
        return explode('|', substr($value, 1, -1));
    }

    public function filterList(\Gazelle\Manager\User $userMan): array {
        self::$db->prepared_query("
            SELECT ID,
                Label,
                Artists,
                ExcludeVA,
                NewGroupsOnly,
                Tags,
                NotTags,
                RecordLabels,
                ReleaseTypes,
                Categories,
                Formats,
                Encodings,
                Media,
                FromYear,
                ToYear,
                Users
            FROM users_notify_filters
            WHERE UserID = ?
            ", $this->user->id()
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$f) {
            $f['Artists']      = implode("\n", $this->valueToArray($f['Artists']));
            $f['Tags']         = implode("\n", $this->valueToArray($f['Tags']));
            $f['NotTags']      = implode("\n", $this->valueToArray($f['NotTags']));
            $f['RecordLabels'] = $this->valueToArray($f['RecordLabels']);
            $f['ReleaseTypes'] = $this->valueToArray($f['ReleaseTypes']);
            $f['Categories']   = $this->valueToArray($f['Categories']);
            $f['Formats']      = $this->valueToArray($f['Formats']);
            $f['Encodings']    = $this->valueToArray($f['Encodings']);
            $f['Media']        = $this->valueToArray($f['Media']);

            if ($f['FromYear'] == 0) {
                $f['FromYear'] = '';
            }
            if ($f['ToYear'] == 0) {
                $f['ToYear'] = '';
            }

            $userIds = $this->valueToArray($f['Users']);
            $usernames = [];
            foreach ($userIds as $userId) {
                $u = $userMan->findById((int)$userId);
                if (!is_null($u)) {
                    $usernames[] = $u->username();
                }
            }
            $f['Users'] = implode("\n", $usernames);
        }
        return $list;
    }
}
