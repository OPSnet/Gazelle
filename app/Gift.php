<?php

namespace Gazelle;

class Gift extends \Gazelle\BasePgObject {
    final public const tableName = 'gift';
    final public const CACHE_KEY = 'gift_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return '';
    }

    public function location(): string {
        return '';
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->pg()->rowAssocex('
                select g.id_gift,
                    g.label,
                    g.description,
                    g.weight,
                    g.factor,
                    g.duration,
                    count(uhg.id_user) as given_total
                from gift g
                left join user_has_gift uhg using (id_gift)
                where g.id_gift = $1
                group by g.id_gift
                ', $this->id
            );
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $this->info;
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function durationHours(): string {
        return $this->info()['duration'];
    }

    public function factor(): int {
        return $this->info()['factor'];
    }

    public function givenTotal(): int {
        return $this->info()['given_total'];
    }

    public function label(): string {
        return $this->info()['label'];
    }

    public function weight(): int {
        return $this->info()['weight'];
    }

    /**
     * Override the weight of the gift during this session.
     * Used to skew the probability of obtaining a specific gift.
     */
    public function setTemporaryWeight(int $weight): int {
        $oldWeight = $this->weight();
        $this->info['weight'] = $weight;
        return $oldWeight;
    }

    /**
     * Give a gift (of this type) to a user. Returns an id from user_has_gift
     * that can be used to locate both the gift and the user via the gift manager.
     */
    public function giveTo(
        User $user,
        Manager\Bonus $bonusMan = new Manager\Bonus(),
    ): int {
        self::$db->begin_transaction();
        $userHasGiftId = $this->pg()->scalarex("
            insert into user_has_gift
                   (id_user, id_gift)
            values ($1,      $2)
            returning id_user_has_gift
            ", $user->id, $this->id
        );
        if ($this->durationHours() > 0) {
            $this->pg()->query("
                insert into user_has_gift_validity
                       (id_user_has_gift, validity)
                values ($1, tstzrange(now(), now() + '1 hour'::interval * $2)
                ", $userHasGiftId, $this->durationHours()
            );
        }
        if (str_starts_with($this->label(), 'up-')) {
            $user->setField(
                'leech_upload',
                $user->uploadedSize() + $this->factor() * 1024 ** 3
            )->modify();
        } elseif (str_starts_with($this->label(), 'down-')) {
            // if reducing download would put their metric into negative
            // territory, add the surplus to their upload metric.
            $reduction = $this->factor() * 1024 ** 3;
            $currentDown = $user->downloadedSize();
            if ($currentDown > $reduction) {
                $user->setField('leech_download', $currentDown - $reduction);
            } else {
                $user->setField('leech_download', 0)
                    ->setField('leech_upload',
                        $user->uploadedSize() + $reduction - $currentDown
                    );
            }
            $user->modify();
        } elseif (str_starts_with($this->label(), 'bonus-')) {
            if (!$user->disableBonusPoints()) {
                new User\Bonus($user)->addPoints($this->factor());
            }
        } elseif ($this->label() === 'collage') {
            $bonusMan->findBonusItemByLabel('collage-1')->purchase($user, 0);
        } elseif (str_starts_with($this->label(), 'reqc-')) {
            $list = $this->pg()->columnex('
                select id_request
                from request
                where id_torrent is null
                    and id_user = $1
                ', $user->id
            );
            if ($list === []) {
                $this->giftRequestFail($user, 'gift/request-create-fail.bbcode.twig');
            } else {
                $this->giftRequestSuccess(
                    new Request($list[random_int(0, count($list) - 1)]),
                    $user,
                    'gift/request-create-success.bbcode.twig',
                );
            }
        } elseif (str_starts_with($this->label(), 'reqv-')) {
            $list = $this->pg()->columnex('
                select distinct r.id_request
                from request r
                inner join request_vote rv using (id_request)
                where r.id_torrent is null
                    and rv.id_user = $1
                ', $user->id
            );
            if ($list === []) {
                $this->giftRequestFail($user, 'gift/request-vote-fail.bbcode.twig');
            } else {
                $this->giftRequestSuccess(
                    new Request($list[random_int(0, count($list) - 1)]),
                    $user,
                    'gift/request-vote-success.bbcode.twig'
                );
            }
        } elseif (str_starts_with($this->label(), 'token-')) {
            $user->updateTokens($user->tokenCount() + $this->factor());
        }
        self::$db->commit();
        $this->flush(); // let the total be recalculated
        return $userHasGiftId;
    }

    protected function giftRequestFail(
        User   $user,
        string $template
    ): int {
        return $user->inbox()->createSystem(
            "Your gift to vote on a request could not be delivered",
            self::$twig->render($template, [
                'gift' => $this,
                'user' => $user,
            ]),
        )->id;
    }

    protected function giftRequestSuccess(
        Request $request,
        User    $user,
        string  $template
    ): int {
        // add the bounty to the user so that they can vote on the request
        $bounty = $this->factor() * 1024 ** 3;
        $user->setField('leech_upload', $user->uploadedSize() + $bounty)->modify();
        $request->vote($user, $bounty);
        return $user->inbox()->createSystem(
            "{$this->factor()} GiB was added to {$request->title()}",
            self::$twig->render($template, [
                'gift'    => $this,
                'request' => $request,
                'user'    => $user,
            ]),
        )->id;
    }
}
