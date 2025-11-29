<?php

namespace Gazelle;

use Gazelle\Enum\GiftStatus;
use sad_spirit\pg_wrapper\types\DateTimeRange;

class GiftEvent extends \Gazelle\BasePgObject {
    final public const tableName = 'gift_event';
    final public const pkName    = 'id_gift_event';
    final public const CACHE_KEY = 'gift_ev_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return "<a href=\"{$this->url()}\">{$this->name()}</a>";
    }

    public function location(): string {
        return "tools.php?action=gift-event&id={$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->pg()->rowAssocex('
                select ge.id_gift_event,
                    ge.name,
                    ge.open,
                    ge.id_user,
                    count(distinct uhg.id_user) as total_user,
                    count(uhg.id_gift)          as total_gift,
                    avg(g.weight::real)         as avg_weight
                from gift_event ge
                left join gift_event_user_has_gift geuhe using (id_gift_event)
                left join user_has_gift uhg using (id_user_has_gift)
                left join gift g using (id_gift)
                where id_gift_event = $1
                group by ge.id_gift_event,
                    ge.open,
                    ge.id_user
                ', $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function averageWeight(): float {
        // cast null of new events to 0.0
        return (float)$this->info()['avg_weight'];
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function isOpen(): bool {
        $now = new \DateTime();
        return $this->openBegin() <= $now && $now <= $this->openEnd();
    }

    public function open(): DateTimeRange {
        return $this->info()['open'];
    }

    public function openBegin(): \DateTimeImmutable {
        return $this->open()->lower;
    }

    public function openEnd(): \DateTimeImmutable {
        return $this->open()->upper;
    }

    public function totalGift(): int {
        return $this->info()['total_gift'];
    }

    public function totalUser(): int {
        return $this->info()['total_user'];
    }

    public function userId(): int {
        return $this->info()['id_user'];
    }

    public function isGiftAvailable(User $user): bool {
        $key = sprintf(Manager\Gift::AVAIL_KEY, $user->id);
        $available = self::$cache->get_value($key);
        if ($available === false) {
            [$available, $expiry] = $this->pg()->rowex("
                with ge(id_gift_event, is_open, event_end) as (
                    select id_gift_event, now() <@ open, upper(open)
                    from gift_event
                    where id_gift_event = $1
                ),
                recent(created) as (
                    select max(created)
                    from user_has_gift uhg
                    inner join gift_event_user_has_gift geuhg using (id_user_has_gift)
                    inner join ge using (id_gift_event)
                    where uhg.id_user = $2
                )
                select case
                    when ge.is_open and (recent.created is null or recent.created::date < now()::date)
                        then 'yes'
                        else 'no'
                        end as allowed,
                    extract(epoch from (now() + interval '1 day')::date::timestamptz - now())::integer
                        as expiry
                    from ge
                    cross join recent;
                ", $this->id, $user->id
            );
            // NB: we could not cache a literal false which is why it was stringified in the query
            self::$cache->cache_value($available, $key, $expiry);
        }
        return $available === 'yes';
    }

    public function allocateGift(
        string       $day,
        string       $signature,
        int          $userId,
        Manager\Gift $giftMan = new Manager\Gift(),
        Manager\User $userMan = new Manager\User(),
    ): array {
        if (!$this->isOpen()) {
            return [GiftStatus::eventClosed, null];
        }
        if ($day !== date('Y-m-d')) {
            return [GiftStatus::linkExpired, null];
        }
        if ($signature != signature(implode('|', [$this->id, $day, $userId]), GIFT_SALT)) {
            return [GiftStatus::invalidParameter, null];
        }
        $user = $userMan->findById($userId);
        if (is_null($user)) {
            return [GiftStatus::badParameter, null];
        }

        if (!$this->isGiftAvailable($user)) {
            return [GiftStatus::tooEarly, null];
        }
        $gift = $giftMan->choose();
        $this->pg()->beginex();
        $this->pg()->query('
            insert into gift_event_user_has_gift
                   (id_gift_event, id_user_has_gift)
            values ($1,            $2)
            ', $this->id, $gift->giveTo($user)
        );
        $this->pg()->commitex();
        $this->flush();
        return [GiftStatus::success, $gift];
    }

    public function userPage(
        int $limit,
        int $offset,
        string $orderBy, // TODO: sortable table header
        string $dir,
    ): array {
        return $this->pg()->allex(<<<END_SQL
            select uhg.id_user,
                um."Username"      as username,
                count(uhg.id_gift) as total,
                min(uhg.created)   as first,
                max(uhg.created)   as last
            from user_has_gift uhg
            inner join gift_event_user_has_gift geuhg using (id_user_has_gift)
            inner join relay.users_main um on (um."ID" = uhg.id_user)
            where geuhg.id_gift_event = $1
            group by uhg.id_user,
                um."Username"
            order by {$orderBy} {$dir}
            limit $2 offset $3
            END_SQL
            , $this->id, $limit, $offset
        );
    }

    public function signature(User $user): string {
        return signature(implode('|', [$this->id, date('Y-m-d'), $user->id]), GIFT_SALT);
    }

    public function userGiftList(
        User $user,
        Manager\Gift $manager = new Manager\Gift(),
    ): array {
        $result = $this->pg()->allex(<<<END_SQL
            select uhg.id_user_has_gift,
                uhg.id_gift,
                uhg.created
            from user_has_gift uhg
            inner join gift_event_user_has_gift geuhg using (id_user_has_gift)
            where geuhg.id_gift_event = $1
                and uhg.id_user = $2
            order by uhg.created
            END_SQL
            , $this->id, $user->id
        );
        $gift = []; // gift cache
        $list = [];
        foreach ($result as $r) {
            $r['gift'] = ($gift[$r['id_gift']] ??= $manager->findById($r['id_gift']));
            $list[] = $r;
        }
        return $list;
    }

    public function giftStats(): array {
        return $this->pg()->allex(<<<END_SQL
            select g.id_gift,
                g.description,
                count(ge.id_gift) as total
            from gift g
            left join (
                select uhg.id_gift
                from gift_event_user_has_gift geuhg
                inner join user_has_gift uhg using (id_user_has_gift)
                where geuhg.id_gift_event = $1
            ) ge using (id_gift)
            group by g.id_gift
            order by g.weight desc
            END_SQL
            , $this->id
        );
    }

    public function dayStats(): array {
        return $this->pg()->allex(<<<END_SQL
            with d(day) as (
                select generate_series(
                    lower(ge.open),
                    upper(ge.open) - interval '1 second',
                    interval '1 day'
                )::date
                from gift_event ge
                where ge.id_gift_event = $1
            ),
            g(total, day) as (
                select count(*),
                    uhg.created::date
                from user_has_gift uhg
                inner join gift_event_user_has_gift geuhg using (id_user_has_gift)
                where geuhg.id_gift_event = $1
                group by uhg.created::date
            )
            select d.day,
                coalesce(g.total, 0) as total
            from d
            left join g using (day)
            order by d.day
            END_SQL
            , $this->id
        );
    }

    /**
     * Assiduity measures how frequently people return day by day to the
     * event. Someone who comes back every day will score the highest
     * value (the number of days in the the event). Someone who opens
     * only one gift scores 1. The assiduity table therefore tallies how
     * many times everyone interacted with the event.
     */
    public function assiduityStats(): array {
        return $this->pg()->allex(<<<END_SQL
            with d(activity) as (
                select generate_series(1, (upper(open)::date - lower(open)::date)::int)
                from gift_event
                where id_gift_event = $1
            ),
            u(activity) as (
                select count(*)
                from user_has_gift uhg
                inner join gift_event_user_has_gift geuhg using (id_user_has_gift)
                where geuhg.id_gift_event = $1
                group by uhg.id_user
            )
            select d.activity,
                count(u.activity) as total
            from d
            left join u using (activity)
            group by d.activity
            order by d.activity
            END_SQL
            , $this->id
        );
    }

    public function close(): int {
        if (!$this->isOpen()) {
            return 0;
        }
        $result = $this->pg()->executeParams('
            update gift_event set
                open = tstzrange(lower(open), now())
            where id_gift_event = $1
            ', $this->id
        );
        $this->flush();
        return $result->getAffectedRows();
    }
}
