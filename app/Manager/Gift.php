<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\User;

class Gift extends \Gazelle\BaseManager {
    final protected const ID_KEY = 'zz_gift_%d';
    final protected const CACHE_LIST = 'gift_list';
    final public const AVAIL_KEY = 'gift_ev_u_%d';

    protected array $info;

    public function flush(): static {
        if (isset($this->info)) {
            foreach ($this->info as $gift) {
                $gift->flush();
            }
            unset($this->info);
        }
        self::$cache->delete_value(self::CACHE_LIST);
        return $this;
    }

    public function findById(int $id): ?\Gazelle\Gift {
        $gift = array_filter(
            $this->giftList(),
            fn ($g) => $g->id() === $id
        );
        return $gift === [] ? null : current($gift);
    }

    public function findByLabel(string $label): ?\Gazelle\Gift {
        $gift = array_filter(
            $this->giftList(),
            fn ($g) => $g->label() === $label
        );
        return $gift === [] ? null : current($gift);
    }

    public function giftList(): array {
        if (!isset($this->info)) {
            $idList = self::$cache->get_value(self::CACHE_LIST);
            if ($idList === false) {
                $idList = $this->pg()->columnex("
                    select id_gift from gift
                ");
                self::$cache->cache_value(self::CACHE_LIST, $idList, 0);
            }
            $this->info = array_map(
                fn ($id) => new \Gazelle\Gift($id),
                $idList,
            );
        }
        return $this->info;
    }

    public function giftTotal(): int {
        // NB: not cached (at least for now), but only admins hit this endpoint
        return (int)$this->pg()->scalarex('
            select count(*) from user_has_gift
        ');
    }

    public function weightTotal(): int {
        return (int)array_sum(
            array_map(fn ($g) => $g->weight(), $this->giftList())
        );
    }

    /**
     * Note: this method returns a random gift. It does not mean that it
     * has been given to a user. For that, a row needs to be inserted into
     * user_has_gift. That is handled by the Gift::giveTo(User) method.
     */
    public function choose(): \Gazelle\Gift {
        return weightedChoice(
            array_map(
                fn ($g) => [$g, $g->weight()],
                $this->giftList(),
            )
        );
    }
}
