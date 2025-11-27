<?php

namespace Gazelle;

class BonusItem extends \Gazelle\BasePgObject {
    final public const tableName = 'bonus_item';
    final public const CACHE_KEY = 'bonus_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return '';
    }

    public function location(): string {
        return 'bonus.php';
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->pg()->rowAssocex('
                select price,
                    amount,
                    userclass_min,
                    userclass_free,
                    prepare,
                    frequency,
                    label,
                    title
                from bonus_item
                where id_bonus_item = $1
                ', $this->id
            );
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $this->info;
    }

    public function amount(): int {
        return $this->info()['amount'];
    }

    public function label(): string {
        return $this->info()['label'];
    }

    public function needsPreparation(): bool {
        return $this->info()['prepare'];
    }

    public function isRecurring(): bool {
        return $this->info()['frequency'] === 'recurring';
    }

    /**
     * Even after userclass levels are taken into account, there may exist other
     * reasons to deny a user from buying an item from the shop.
     */
    public function permitted(User $user): bool {
        return match ($this->label()) {
            'invite' => !$user->disableInvites(),
            default  => true,
        };
    }

    public function price(): int {
        return $this->info()['price'];
    }

    public function priceForTokenOther(User $giver, User $receiver): int|false {
        if (!str_starts_with($this->label(), 'other-')) {
            return false;
        }
        // Inviters and special people send tokens at a flat rate.
        if ($receiver->inviterId() === $giver->id || $giver->hasAttr('bypass-bonus-scale')) {
            return $this->price();
        }
        $exchange = new User\Bonus($receiver)->tokenExchange();
        // For every BONUS_OTHER_TOKEN_INTERVAL received,
        // scale the price up by BONUS_OTHER_TOKEN_SCALE percent.
        return (int)ceil(
            $this->price()
            * pow(
                1 + (BONUS_OTHER_TOKEN_SCALE / 100),
                floor(max(0, $exchange['received'] - $exchange['sent'])
                    / BONUS_OTHER_TOKEN_INTERVAL
                )
            )
        );
    }

    public function priceForUser(User $user): int {
        return match ($this->label()) {
            'collage-1' => $this->price() * 2 ** min(6, $user->paidPersonalCollages()),
            default     => $user->classLevel() >= $this->userclassFree()
                ? 0 : $this->price(),
        };
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function userclassFree(): int {
        return $this->info()['userclass_free'];
    }

    public function userclassMin(): int {
        return $this->info()['userclass_min'];
    }

    public function isPurchased(User $user): bool {
        return (bool)$this->pg()->scalarex('
            select exists (
                select 1
                from relay.bonus_history bh
                where bh."ItemID" = $1
                    and bh."UserID" = $2
            )
            ', $this->id, $user->id
        );
    }

    /* Object-orient orthodoxy would suggest creating classes for each type
     * of item that can be purchased, and calling the purchase() method
     * through polymorphism. That would be a lot of boilerplate code simply
     * to provide one method. Rules are made to be broken, so we will cheat
     * and use a humongous switch. New items will need their own effect
     * added here. An unintended benefit is that determining the price and
     * recording the purchase history happen in one place.
     *
     * Note: the price of the item is passed in as a parameter. This may
     * seem a little weird, because the method call looks something like:
     *   $item->purchase($user, $item->price());
     * ... but it allows the gifting of something to a user for free:
     *   $item->purchase($user, 0);
     */
    public function purchase(User $user, int $price, array $extra = []): Enum\BonusItemPurchaseStatus {
        if ($user->classLevel() < $this->userclassMin()) {
            return Enum\BonusItemPurchaseStatus::forbidden;
        }
        if ($this->isPurchased($user) && !$this->isRecurring()) {
            return Enum\BonusItemPurchaseStatus::alreadyPurchased;
        }
        if ($user->classLevel() >= $this->userclassFree()) {
            $price = 0;
        }

        if (str_starts_with($this->label(), 'token-')) {
            self::$db->prepared_query('
                UPDATE user_bonus ub
                INNER JOIN user_flt uf USING (user_id) SET
                    uf.tokens = uf.tokens + ?,
                    ub.points = ub.points - ?
                WHERE ub.points >= ?
                    AND ub.user_id = ?
                ', $this->amount(), $price, $price, $user->id
            );
            if (self::$db->affected_rows() != 1 + ($price === 0 ? 0 : 1)) {
                return Enum\BonusItemPurchaseStatus::insufficientFunds;
            }
        } elseif (str_starts_with($this->label(), 'other-')) {
            if (!isset($extra['receiver'])) {
                return Enum\BonusItemPurchaseStatus::incomplete;
            }
            $receiver = $extra['receiver'];
            if ($user->id === $receiver->id || !$receiver->isEnabled()) {
                return Enum\BonusItemPurchaseStatus::forbidden;
            }
            if ($receiver->hasAttr('no-fl-gifts')) {
                return Enum\BonusItemPurchaseStatus::declined;
            }
            /* Take the bonus points from the giver and give tokens to the
             * receiver, unless the latter have asked to refuse receiving
             * tokens. Yes, we have just checked this and possibly sent a
             * 'declined' response. But race conditions being what they
             * are, the proof is in the database update. If this fails,
             * we will consider it is due to insufficient funds (the more
             * likely scenario anyway) and the purchaser can try again.
             */
            self::$db->prepared_query("
                UPDATE user_bonus ub
                INNER JOIN users_main self ON (self.ID = ub.user_id),
                    users_main other
                    INNER JOIN user_flt other_uf ON (other_uf.user_id = other.ID)
                    LEFT JOIN user_has_attr noFL ON (noFL.UserID = other.ID AND noFL.UserAttrId
                        = (SELECT ua.ID FROM user_attr ua WHERE ua.Name = 'no-fl-gifts')
                    )
                SET
                    other_uf.tokens = other_uf.tokens + ?,
                    ub.points = ub.points - ?
                WHERE noFL.UserID IS NULL
                    AND other.Enabled = ?
                    AND other.ID = ?
                    AND self.ID = ?
                    AND ub.points >= ?
                ", $this->amount(), $price,
                    Enum\UserStatus::enabled->value, $receiver->id, $user->id, $price
            );
            if (self::$db->affected_rows() != 1 + ($price === 0 ? 0 : 1)) {
                return Enum\BonusItemPurchaseStatus::insufficientFunds;
            }
            $amount = $this->amount();
            $receiver->inbox()->createSystem(
                "Here " . ($amount == 1 ? 'is' : 'are') . ' ' . article($amount)
                    . " freeleech token" . plural($amount) . "!",
                self::$twig->render('bonus/token-other-message.bbcode.twig', [
                    'amount'   => $amount,
                    'from'     => $user->username(),
                    'to'       => $receiver->username(),
                    'message'  => $extra['message'] ?? '',
                    'wiki_id'  => 57,
                ])
            );
        } else {
            switch ($this->label()) {
                case 'collage-1':
                    $price = (int)($price * 2 ** $user->paidPersonalCollages());
                    self::$db->begin_transaction();
                    self::$db->prepared_query('
                        UPDATE user_bonus ub
                        INNER JOIN users_main um ON (um.ID = ub.user_id) SET
                            ub.points = ub.points - ?,
                            um.collage_total = um.collage_total + 1
                        WHERE ub.points >= ?
                            AND ub.user_id = ?
                        ', $price, $price, $user->id
                    );
                    if (self::$db->affected_rows() != 1 + ($price === 0 ? 0 : 1)) {
                        self::$db->rollback();
                        return Enum\BonusItemPurchaseStatus::insufficientFunds;
                    }
                    $user->ordinal()
                        ->set(
                            'personal-collage',
                            (int)self::$db->scalar("
                                SELECT collage_total FROM  users_main WHERE ID = ?
                                ", $user->id
                            )
                        );
                    self::$db->commit();
                    break;

                case 'file-count':
                    self::$db->begin_transaction();
                    self::$db->prepared_query('
                        UPDATE user_bonus ub SET
                            ub.points = ub.points - ?
                        WHERE ub.points >= ?
                            AND ub.user_id = ?
                        ', $price, $price, $user->id
                    );
                    if ($price > 0 && self::$db->affected_rows() != 1) {
                        self::$db->rollback();
                        return Enum\BonusItemPurchaseStatus::insufficientFunds;
                    }
                    try {
                        self::$db->prepared_query("
                            INSERT INTO user_has_attr
                                   (UserID, UserAttrID)
                            VALUES (?,      (SELECT ID FROM user_attr WHERE Name = ?))
                            ", $user->id, 'feature-file-count'
                        );
                    } catch (\Gazelle\DB\MysqlDuplicateKeyException) {
                        // no point in buying a second time
                        self::$db->rollback();
                        return Enum\BonusItemPurchaseStatus::alreadyPurchased;
                    }
                    self::$db->commit();
                    break;

                case 'invite':
                    if (!$user->canPurchaseInvite()) {
                        return Enum\BonusItemPurchaseStatus::forbidden;
                    }
                    self::$db->prepared_query('
                        UPDATE user_bonus ub
                        INNER JOIN users_main um ON (um.ID = ub.user_id) SET
                            ub.points = ub.points - ?,
                            um.Invites = um.Invites + 1
                        WHERE ub.points >= ?
                            AND ub.user_id = ?
                        ', $price, $price, $user->id
                    );
                    if (self::$db->affected_rows() != 1 + ($price === 0 ? 0 : 1)) {
                        return Enum\BonusItemPurchaseStatus::insufficientFunds;
                    }
                    break;

                case 'seedbox':
                    self::$db->begin_transaction();
                    self::$db->prepared_query('
                        UPDATE user_bonus ub SET
                            ub.points = ub.points - ?
                        WHERE ub.points >= ?
                            AND ub.user_id = ?
                        ', $price, $price, $user->id
                    );
                    if ($price > 0 && self::$db->affected_rows() != 1) {
                        self::$db->rollback();
                        return Enum\BonusItemPurchaseStatus::insufficientFunds;
                    }
                    try {
                        self::$db->prepared_query("
                            INSERT INTO user_has_attr
                                   (UserID, UserAttrID)
                            VALUES (?,      (SELECT ID FROM user_attr WHERE Name = ?))
                            ", $user->id, 'feature-seedbox'
                        );
                    } catch (DB\MysqlDuplicateKeyException) {
                        // no point in buying a second time
                        self::$db->rollback();
                        return Enum\BonusItemPurchaseStatus::alreadyPurchased;
                    }
                    self::$db->commit();
                    break;

                case 'title-bb-n':
                case 'title-bb-y':
                    if (!isset($extra['title'])) {
                        return Enum\BonusItemPurchaseStatus::incomplete;
                    }
                    if ($price > 0 && !new User\Bonus($user)->removePoints($price)) {
                        return Enum\BonusItemPurchaseStatus::insufficientFunds;
                    }
                    if (
                        !$user->setTitle(
                            $this->label() === 'title-bb-y'
                                ? \Text::span_format($extra['title'])
                                : \Text::strip_bbcode($extra['title'])
                        )
                    ) {
                        return Enum\BonusItemPurchaseStatus::incomplete;
                    }
                    $user->modify();
                    break;

                case 'title-off':
                    $user->removeTitle()->modify();
                    break;
            }
        }
        self::$db->prepared_query("
            INSERT INTO bonus_history
                   (ItemID, UserID, Price, OtherUserID)
            VALUES (?,      ?,      ?,     ?)
            ", $this->id,
                $user->id,
                $price,
                isset($extra['receiver']) ? $extra['receiver']->id : 0,
        );
        new User\Bonus($user)->flush();
        $user->flush();

        return Enum\BonusItemPurchaseStatus::success;
    }
}
