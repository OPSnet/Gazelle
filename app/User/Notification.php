<?php

namespace Gazelle\User;

class Notification extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final public const tableName    = 'users_notifications_settings';
    final protected const CACHE_KEY = 'u_notif2_%d';

    final public const DISPLAY_DISABLED         = 0;
    final public const DISPLAY_POPUP            = 1;
    final public const DISPLAY_TRADITIONAL      = 2;
    final public const DISPLAY_PUSH             = 3;
    final public const DISPLAY_POPUP_PUSH       = 4;
    final public const DISPLAY_TRADITIONAL_PUSH = 5;

    protected array  $alert;
    protected array  $config;
    protected string $pushToken;
    protected string $document;
    protected string $action;

    // for results page
    protected bool $dirty = true;
    protected bool $orderByYear = false;
    protected string $baseQuery;
    protected array $cond = [];
    protected array $args = [];

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function config(): array {
        if (isset($this->config)) {
            return $this->config;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id());
        $config = self::$cache->get_value($key);
        if ($config === false) {
            $attributes = $this->pg()->column("
                select ua.name
                from user_attr ua
                inner join user_has_attr uha using (id_user_attr)
                inner join user_attr_notification uhan using (id_user_attr)
                where uha.id_user = ?;
                ", $this->id()
            );

            $typeConfig = [];
            foreach (\Gazelle\Enum\NotificationType::cases() as $type) {
                $type = $type->toString();
                $typeConfig += [$type => 0];
                $lowerType = strtolower($type);
                $pop = in_array("{$lowerType}-pop", $attributes);
                $trad = in_array("{$lowerType}-trad", $attributes);
                $push = in_array("{$lowerType}-push", $attributes);
                if ($trad && $push) {
                    $typeConfig[$type] = self::DISPLAY_TRADITIONAL_PUSH;
                } elseif ($pop && $push) {
                    $typeConfig[$type] = self::DISPLAY_POPUP_PUSH;
                } elseif ($push) {
                    $typeConfig[$type] = self::DISPLAY_PUSH;
                } elseif ($trad) {
                    $typeConfig[$type] = self::DISPLAY_TRADITIONAL;
                } elseif ($pop) {
                    $typeConfig[$type] = self::DISPLAY_POPUP;
                }
            }
            $config = $typeConfig;
            self::$cache->cache_value($key, $config, 0);
            $this->config = $config;
        }
        return $config;
    }

    public function pushToken(): string {
        return $this->pushToken ??= (string)$this->pg()->scalar("
            SELECT push_token
            FROM user_push_options AS pn
            WHERE pn.id_user = ?
            ", $this->user->id()
        );
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
            $alert['Global'] = $global->setDisplay($noty ? self::DISPLAY_POPUP : self::DISPLAY_TRADITIONAL);
        }
        $this->alert = $alert;
        return $this->alert;
    }

    public function useNoty(): bool {
        return (bool)count(array_filter($this->alertList(),
            fn ($a) => in_array($a->display(), [self::DISPLAY_POPUP, self::DISPLAY_POPUP_PUSH])));
    }

    public function save(array $settings): int {
        $selected = [];
        $unselected = [];

        foreach (\Gazelle\Enum\NotificationType::cases() as $type) {
            $type = $type->toString();
            // check settings for this type
            $push  = in_array('notifications_' . $type . '_push', $settings);
            $trad  = in_array('notifications_' . $type . '_traditional', $settings);
            $popup = in_array('notifications_' . $type . '_popup', $settings);
            $type  = strtolower($type);

            // write settings to selected[] or unselected[]
            $push ? $selected[] = $type . "-push" : $unselected[] = $type . "-push";
            $trad ? $selected[] = $type . "-trad" : $unselected[] = $type . "-trad";
            $popup ? $selected[] = $type . "-pop" : $unselected[] = $type . "-pop";
        }

        $userId = $this->user->id();

        $affected = 0;
        foreach ($selected as $attr) {
            $affected += $this->pg()->prepared_query("
                insert into user_has_attr (id_user, id_user_attr)
                values (?, (select id_user_attr from user_attr where name = ?))
                on conflict do nothing;
            ", $userId, $attr);
        }

        foreach ($unselected as $attr) {
            $affected += $this->pg()->prepared_query("
                delete from user_has_attr
                where id_user = ?
                    and id_user_attr = (select id_user_attr from user_attr where name = ?);
            ", $userId, $attr);
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

    public function setPushTopic(string $newTopic): bool {
        return $this->pg()->prepared_query("
            insert into user_push_options
            values (?, ?, default)
            on conflict (id_user)
            do update set push_token = excluded.push_token;
            ", $this->user->id, $newTopic
        ) === 1;
    }
}
