<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\User;

class GiftEvent extends \Gazelle\BaseManager {
    final protected const ID_KEY = 'zz_ge_%d';
    final protected const CACHE_LIST = 'gift_event_list';
    protected array $giftList;

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_LIST);
        return $this;
    }

    public function create(
        string $name,
        string $begin,
        string $end,
        User   $user,
    ): \Gazelle\GiftEvent {
        $giftEvent = new \Gazelle\GiftEvent(
            $this->pg()->scalarex('
                insert into gift_event
                       (id_user, name, open)
                values ($1,      $2,    tstzrange($3, $4))
                returning id_gift_event
                ', $user->id, $name, $begin, $end
            )
        );
        $this->flush();
        return $giftEvent;
    }

    public function findById(int $id): ?\Gazelle\GiftEvent {
        $key = sprintf(self::ID_KEY, $id);
        $giftEventId = self::$cache->get_value($key);
        if ($giftEventId === false) {
            $giftEventId = (int)$this->pg()->scalarex('
                select id_gift_event from gift_event where id_gift_event = $1
                ', $id
            );
            if ($giftEventId) {
                self::$cache->cache_value($key, $giftEventId, 0);
            }
        }
        return $giftEventId ? new \Gazelle\GiftEvent($giftEventId) : null;
    }

    public function findOpenEvent(): ?\Gazelle\GiftEvent {
        $open = array_filter($this->eventList(), fn ($e) => $e->isOpen());
        return $open !== [] ? current($open) : null;
    }

    public function eventList(): array {
        $idList = self::$cache->get_value(self::CACHE_LIST);
        if ($idList === false) {
            $idList = $this->pg()->columnex('
                select ge.id_gift_event
                from gift_event ge
                order by now() <@ ge.open desc,
                    lower(ge.open) desc
            ');
            self::$cache->cache_value(self::CACHE_LIST, $idList, 0);
        }
        return array_map(fn ($id) => new \Gazelle\GiftEvent($id), $idList);
    }
}
