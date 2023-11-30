<?php

namespace Gazelle\User;

class Donor extends \Gazelle\BaseUser {
    final const tableName     = 'donor_rewards';
    final const pkName        = 'UserID';
    protected const CACHE_KEY = 'donor_%d';

    protected bool $isDonor;

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id()));
        unset($this->isDonor);
        $this->info = [];
        return $this;
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT udr.donor_rank,
                    udr.SpecialRank         AS special_rank,
                    udr.TotalRank           AS total_rank,
                    udr.Hidden              AS hidden,
                    udr.InvitesReceivedRank AS invites_received,
                    udr.DonationTime        AS last_donation_date,
                    udr.RankExpirationTime
                        + INTERVAL 766 HOUR AS expiry_date,
                    dr.IconMouseOverText    AS icon_hover_text,
                    dr.AvatarMouseOverText  AS avatar_hover_text,
                    dr.CustomIcon           AS icon,
                    dr.CustomIconLink       AS icon_link,
                    dr.SecondAvatar         AS second_avatar,
                    dr.ProfileInfo1         AS profile_info_1,
                    dr.ProfileInfo2         AS profile_info_2,
                    dr.ProfileInfo3         AS profile_info_3,
                    dr.ProfileInfo4         AS profile_info_4,
                    dr.ProfileInfoTitle1    AS profile_title_1,
                    dr.ProfileInfoTitle2    AS profile_title_2,
                    dr.ProfileInfoTitle3    AS profile_title_3,
                    dr.ProfileInfoTitle4    AS profile_title_4,
                    dfu.Prefix              AS forum_prefix,
                    dfu.Suffix              AS forum_suffix,
                    dfu.Usecomma            AS forum_use_comma
                FROM users_donor_ranks udr
                LEFT JOIN donor_rewards         dr  USING (UserID)
                LEFT JOIN donor_forum_usernames dfu USING (UserID)
                WHERE udr.UserID = ?
                ", $this->id()
            ) ?? [
                'donor_rank'          => 0,
                'special_rank'        => 0,
                'total_rank'          => 0,
                'invites_received'    => 0,
                'hidden'              => '0',
                'avatar_hover_text'   => '',
                'last_donation_time'  => null,
                'expiry_date'         => null,
                'icon_hover_text'     => null,
                'icon'                => null,
                'icon_link'           => null,
                'second_avatar'       => null,
                'profile_info_1'      => null,
                'profile_info_2'      => null,
                'profile_info_3'      => null,
                'profile_info_4'      => null,
                'profile_title_1'     => null,
                'profile_title_2'     => null,
                'profile_title_3'     => null,
                'profile_title_4'     => null,
                'forum_prefix'        => '',
                'forum_suffix'        => '',
                'forum_use_comma'     => 0,
            ];
            $info['hidden']          = ($info['hidden'] === 1);
            $info['forum_use_comma'] = (bool)$info['forum_use_comma'];
            if ($this->user()->isStaff()) {
                $info['donor_rank']   = MAX_EXTRA_RANK;
                $info['special_rank'] = MAX_SPECIAL_RANK;
            }
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function isDonor(): bool {
        return $this->isDonor ??= $this->user->isStaff() || (new Privilege($this->user))->hasSecondaryClass('Donor');
    }

    public function isVisible(): bool {
        return !$this->info()['hidden'];
    }

    public function setVisible(bool $visible): bool {
        self::$db->prepared_query("
            UPDATE users_donor_ranks SET
                Hidden = ?
            WHERE UserID = ?
            ", $visible ? 0 : 1, $this->id()
        );
        return $this->flush()->isVisible();
    }

    /**
     * When does this rank expire?
     */
    public function expirationDate(): ?string {
        return $this->isDonor() ? $this->info()['expiry_date'] : null;
    }

    /**
     * When did the user last donate?
     */
    public function lastDonationDate(): ?string {
        return $this->isDonor() && isset($this->info()['last_donation_date'])
            ? $this->info()['last_donation_date']
            : null; // will not be set for Staff
    }

    public function leaderboardRank(): int {
        return (int)self::$db->scalar("
            SELECT position
            FROM (
                SELECT UserID,
                    row_number() OVER (ORDER BY TotalRank DESC) AS position
                FROM users_donor_ranks
            ) LEADER
            WHERE LEADER.UserID = ?
            ", $this->id()
        );
    }

    /**
     * Current Donor rank (points)
     */
    public function rank(): int {
        return $this->info()['donor_rank'];
    }

    /**
     * Special Donor rank of user
     */
    public function specialRank(): int {
        return $this->info()['special_rank'];
    }

    /**
     * Total Donor points (to help calculate special rank)
     */
    public function totalRank(): int {
        return $this->info()['total_rank'];
    }

    public function hasMaxSpecialRank(): bool {
        return $this->specialRank() === MAX_SPECIAL_RANK;
    }

    public function hasRankAbove(int $rank): bool {
        return $this->hasMaxSpecialRank() || $this->rank() > $rank;
    }

    // The donor rewards

    /**
     * The hover avatar
     */
    public function avatarHover(): string|false {
        return $this->specialRank() > 1 && $this->info()['second_avatar'] ? $this->info()['second_avatar'] : false;
    }

    public function updateAvatarHover(string $value): static {
        return $this->specialRank() > 1 ? $this->setField("SecondAvatar", trim($value)) : $this;
    }

    /**
     * The text of the hover avatar
     */
    public function avatarHoverText(): string|false|null {
        return $this->hasRankAbove(2) ? $this->info()['avatar_hover_text'] : false;
    }

    public function updateAvatarHoverText(string $value): static {
        return $this->hasRankAbove(2) ? $this->setField("AvatarMouseOverText", trim($value)) : $this;
    }

    /**
     * How many collages does the user have thanks to their donations?
     */
    public function collageTotal(): int {
        return $this->hasMaxSpecialRank()
            ? 5
            : min($this->rank(), 5); // One extra collage per donor rank up to 5
    }

    /**
     * Does the user have a donor pick?
     */
    public function hasDonorPick(): bool {
        return (bool)$this->specialRank();
    }

    /**
     * Does the user have access to the donor forum?
     */
    public function hasForum(): bool {
        return $this->hasRankAbove(1);
    }

    public function forumPrefix(): string|false {
        return $this->hasRankAbove(4) ? (string)$this->info()['forum_prefix'] : false;
    }

    public function forumSuffix(): string|false {
        return $this->hasRankAbove(4) ? (string)$this->info()['forum_suffix'] : false;
    }

    public function forumUseComma(): bool {
        return $this->hasRankAbove(4) && $this->info()['forum_use_comma'];
    }

    public function invitesReceived(): int {
        return $this->info()['invites_received'];
    }

    /**
     * The custom icon image
     */
    public function icon(): ?string {
        return $this->hasRankAbove(MAX_RANK) ? $this->info()['icon'] : null;
    }

    public function updateIcon(string $value): static {
        return $this->hasRankAbove(MAX_RANK) ? $this->setField("CustomIcon", trim($value)) : $this;
    }

    /**
     * The link to which the custom donor heart points
     */
    public function iconLink(): string {
        if (!$this->hasRankAbove(3)) {
            return 'donate.php';
        }
        $link = $this->info()['icon_link'];
        return $link ? display_str($link) : 'donate.php';
    }

    /**
     * Value to show for editing
     */
    public function iconLinkValue(): string {
        return (string)$this->info()['icon_link'];
    }

    public function updateIconLink(string $value): static {
        return $this->hasRankAbove(1) ? $this->setField("CustomIconLink", $value) : $this;
    }

    /**
     * The text of the custom donor heart on hover
     */
    public function iconHoverText(): string {
        $text = 'Donor';
        if ($this->hasRankAbove(1)) {
            $custom = $this->info()['icon_hover_text'];
            if (!empty($custom)) {
                $text = $custom;
            }
        }
        return $text;
    }

    /**
     * Value to show for editing
     */
    public function iconHoverTextValue(): string {
        return (string)$this->info()['icon_hover_text'];
    }

    public function updateIconHoverText(string $value): static {
        return $this->hasRankAbove(1) ? $this->setField("IconMouseOverText", $value) : $this;
    }

    /**
     * The custom donor heart
     */
    public function heartIcon(): ?string {
        $icon = $this->hasRankAbove(MAX_RANK) ? $this->icon() : false;
        if ($icon) {
            return image_cache_encode($icon);
        }
        $rank = $this->rank();
        return STATIC_SERVER . "/common/symbols/" . match (true) {
            $this->hasMaxSpecialRank() => "donor_6.png",
            ($rank >= MAX_RANK)        => "donor_5.png",
            ($rank === 5)              => "donor_4.png", // Two points between rank 4 and 5
            in_array($rank, [2, 3, 4]) => "donor_{$rank}.png",
            default                    => "donor.png",
        };
    }

    public function heart(\Gazelle\User $viewer): string {
        if (!$this->isDonor()) {
            return '';
        }
        $override = $this->user->isStaff() ? false : $viewer->permitted('users_override_paranoia');
        if (!$override && !$this->user->propertyVisible($viewer, 'hide_donor_heart')) {
            return '';
        }
        return "<a target=\"_blank\" href=\"{$this->iconLink()}\"><img class=\"donor_icon tooltip\" src=\"{$this->heartIcon()}\" title=\"{$this->iconHoverText()}\" /></a>";
    }

    /**
     * Extra profile info for a given donor level
     *
     * If the user has not unlocked the reward, return false.
     * If the user has unlocked the reward but has not used it, return null.
     * Otherwise, return the text.
     */
    public function profileInfo(int $level): string|null|false {
        return $this->hasRankAbove($level) ? $this->info()["profile_info_$level"] : false;
    }

    /**
     * Update the profile info
     */
    public function updateProfileInfo(int $level, string $value): static {
        return $this->hasRankAbove($level) ? $this->setField("ProfileInfo$level", trim($value)) : $this;
    }

    /**
     * Title of extra profile info
     *
     * If the user has not unlocked the reward, return false.
     * Otherwise, return the text.
     */
    public function profileTitle(int $level): string|null|false {
        return $this->hasRankAbove($level) ? $this->info()["profile_title_$level"] : false;
    }

    /**
     * Update the profile info title
     */
    public function updateProfileTitle(int $level, string $value): static {
        return $this->hasRankAbove($level) ? $this->setField("ProfileInfoTitle$level", trim($value)) : $this;
    }

    /**
     * Get the donation label
     */
    public function rankLabel(bool $showOverflow = false): string {
        if ($this->hasMaxSpecialRank()) {
            return '&infin; [Diamond]';
        }
        $rank = $this->rank();
        $label = min($rank, MAX_RANK);
        $overflow = $rank - $label;
        if (in_array($label, [5, 6])) {
            $label--;
        }
        if ($showOverflow && $overflow) {
            $label .= " (+$overflow)";
        }

        return $label . match (true) {
            ($rank >= 6) => ' [Gold]',
            ($rank >= 4) => ' [Silver]',
            ($rank >= 3) => ' [Bronze]',
            ($rank >= 2) => ' [Copper]',
            ($rank >= 1) => ' [Red]',
            default      => '',
        };
    }

    /**
     * When does the current donation level expire?
     */
    public function rankExpiry(): string {
        if ($this->hasMaxSpecialRank() || $this->rank() == 1) {
            return 'Never';
        }
        $expirationDate = $this->expirationDate();
        if (!$expirationDate) {
            return '';
        }
        return (strtotime($expirationDate) - time() < 60)
            ? 'Soon'
            : ('in ' . time_diff($expirationDate));
    }

    public function username(bool $decorated): string {
        if (!$decorated) {
            return $this->user->username();
        }
        return implode(' ', array_filter(
            [$this->forumPrefix(), $this->user->username() . ($this->forumUseComma() ? ',' : ''), $this->forumSuffix()],
            fn ($t) => $t
        ));
    }

    /**
     * Get the donation history of the user
     *
     * return an array of keyed arrays [amount, created, currency, reason, source, added_by, donor_rank, total_rank]
     */
    public function historyList(): array {
        if (!$this->isDonor()) {
            return [];
        }
        self::$db->prepared_query("
            SELECT Amount AS amount,
                Time      AS created,
                Currency  AS currency,
                Reason    AS reason,
                Source    AS source,
                AddedBy   AS added_by,
                TotalRank AS total_rank,
                donor_rank
            FROM donations
            WHERE UserID = ?
            ORDER BY Time DESC
            ", $this->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function adjust(int $rankDelta, int $totalDelta, string $reason, \Gazelle\User $adjuster): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_donor_ranks SET
                donor_rank = donor_rank + ?,
                TotalRank = TotalRank + ?
            WHERE UserID = ?
            ", $rankDelta, $totalDelta, $this->id()
        );
        self::$db->prepared_query("
            INSERT INTO donations
                   (UserID, donor_rank, TotalRank, Reason, AddedBy, Amount, xbt, Currency, Source)
            VALUES (?,      ?,          ?,         ?,      ?,       0,      0,   '',       'moderation')
            ", $this->id(), $rankDelta, $totalDelta, trim($reason), $adjuster->id()
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $this->flush();
        return $affected;
    }

    public function donate(
        float $amount,
        float $xbtRate,
        string $source,
        string $reason,
        string $currency    = "EUR",
        ?\Gazelle\User $who = null,
    ): int {
        $currency = trim($currency);
        switch ($currency) {
            case 'XBT':
                $xbtAmount  = $amount;
                $fiatAmount = $amount * $xbtRate;
                break;
            case 'EUR':
                $xbtAmount  = $amount / $xbtRate;
                $fiatAmount = $amount;
                break;
            default:
                $xbtAmount  = (new \Gazelle\Manager\XBT)->fiat2xbt($amount, $currency);
                $fiatAmount = $xbtAmount * $xbtRate;
                break;
        }

        // A rank is acquired for a configured DONOR_RANK_PRICE.
        // Multiple ranks can be acquired at once, but the current rank cannot exceed MAX_EXTRA_RANK
        // The entire number of multiple ranks purchased at donation time are credited to Total ranks.
        // Total ranks acquired (all time) unlock Special ranks.
        $priorRank = $this->rank();
        $rankDelta = (int)floor($fiatAmount / DONOR_RANK_PRICE);

        self::$db->begin_transaction();
        self::$db->prepared_query('
            INSERT INTO users_donor_ranks
                   (UserID, donor_rank, TotalRank)
            VALUES (?,      ?,    ?)
            ON DUPLICATE KEY UPDATE
                donor_rank         = donor_rank + ?,
                TotalRank          = TotalRank  + ?,
                DonationTime       = now(),
                RankExpirationTime = now()
            ', $this->id(),
                $rankDelta, $rankDelta, $rankDelta, $rankDelta
        );
        if (!(bool)self::$db->scalar("SELECT 1 FROM donor_rewards WHERE UserID = ?", $this->id())) {
            self::$db->prepared_query("
                INSERT INTO donor_rewards (
                    UserID, IconMouseOverText, AvatarMouseOverText, CustomIcon, CustomIconLink, SecondAvatar,
                    ProfileInfo1, ProfileInfo2, ProfileInfo3, ProfileInfo4,
                    ProfileInfoTitle1, ProfileInfoTitle2, ProfileInfoTitle3, ProfileInfoTitle4
                ) VALUES (?, '', '', '', '', '', '', '', '', '', '', '', '', '')
                ", $this->id()
            );
        }
        $this->flush(); // so that rank() etc are updated

        // Assign them to the Donor secondary class if it hasn't already been done
        if ($priorRank == 0 && $xbtAmount > 0) {
            $this->addDonorStatus();
        }

        // Send them a thank you PM
        $this->user()->inbox()->createSystem(
            'Your contribution has been received and credited. Thank you!',
            $this->messageBody($currency, $amount, $rankDelta, $this->rank())
        );

        // Now that their rank and total rank has been set, we can calculate their special rank and invites
        $cond = [];
        $args = [];
        $newSpecial = $this->calculateSpecialRank();
        if ($newSpecial != $this->specialRank()) {
            $cond[] = 'SpecialRank = ?';
            $args[] = $newSpecial;
        }

        // One invite given per two ranks gained, up to a certain limit
        $previousInvites = $this->invitesReceived();
        $newInvites = min(MAX_RANK, (int)floor(($this->rank() + 1) / 2) * 2) - $previousInvites;
        if ($newInvites) {
            $cond[] = 'InvitesReceivedRank = InvitesReceivedRank + ?';
            $args[] = $newInvites;
        }
        if ($cond) {
            $columns = implode(', ', $cond);
            self::$db->prepared_query("
                UPDATE users_donor_ranks SET $columns WHERE UserID = ?
                ", ...[...$args, $this->id()]
            );
        }
        if ($newInvites) {
            self::$db->prepared_query('
                UPDATE users_main
                SET Invites = Invites + ?
                WHERE ID = ?
                ', $newInvites, $this->id()
            );
        }

        // Add this donation to our history, with the reason for giving invites
        $reason = trim($reason . " invites prev=$previousInvites given=$newInvites");
        self::$db->prepared_query('
            INSERT INTO donations
                   (UserID, Amount, Source, Reason, Currency, AddedBy, donor_rank, TotalRank, xbt)
            VALUES (?,      ?,      ?,      ?,      ?,        ?,       ?,          ?,         ?)
            ', $this->id(), round($fiatAmount, 2), trim($source), trim($reason), $currency,
                $who?->id() ?? $this->id(), $this->rank(), $this->totalRank(), $xbtAmount
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();

        $this->flush();
        $this->user->flush();
        self::$cache->delete_multi(['donations_month_3', 'donations_month_12']);
        return $affected;
    }

    public function calculateSpecialRank(): int {
        $specialRank = $this->specialRank();
        $totalRank   = $this->totalRank();
        if ($totalRank < 10) {
            $specialRank = 0;
        }

        if ($specialRank < 1 && $totalRank >= 10) {
            $this->user()->inbox()->createSystem(
                "You have Reached Special Donor Rank #1! You've Earned: One Donor Pick. Details Inside.",
                self::$twig->render('donation/special-rank-1.twig', [
                   'forum_url'   => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'staffpm_url' => 'staffpm.php',
                ])
            );
            $specialRank = 1;
        }

        if ($specialRank < 2 && $totalRank >= 20) {
            $this->user()->inbox()->createSystem(
                "You have Reached Special Donor Rank #2! You've Earned: The Double-Avatar. Details Inside.",
                self::$twig->render('donation/special-rank-2.twig', [
                   'forum_url' => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                ])
            );
            $specialRank = 2;
        }

        if ($specialRank < 3 && $totalRank >= 50) {
            $this->user()->inbox()->createSystem(
                "You have Reached Special Donor Rank #3! You've Earned: Diamond Rank. Details Inside.",
                self::$twig->render('donation/special-rank-3.twig', [
                   'forum_url'      => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'forum_gold_url' => 'forums.php?action=viewthread&threadid=178640&postid=4839789#post4839789',
                ])
            );
            $specialRank = 3;
        }
        return $specialRank;
    }

    public function messageBody(string $currency, float $amount, int $receivedRank, int $currentRank): string {
        if ($currency != 'XBT') {
            $amount = number_format($amount, 2);
        }
        if ($currentRank >= MAX_RANK) {
            $currentRank = MAX_RANK - 1;
        } elseif ($currentRank == 5) {
            $currentRank = 4;
        }
        return self::$twig->render('donation/donation-pm.twig', [
            'amount' => $amount,
            'cc'     => $currency,
            'points' => $receivedRank,
            's'      => plural($receivedRank),
            'rank'   => $currentRank,
            'staffpm_url' => 'staffpm.php',
        ]);
    }

    public function setSpecialRank(int $rank): int {
        self::$db->prepared_query("
            UPDATE users_donor_ranks SET
                SpecialRank = ?
            WHERE UserID = ?
            ", $rank, $this->id()
        );
        return $this->flush()->specialRank();
    }

    public function setForumPrefix(string $prefix): bool {
        if (!$this->hasForum()) {
            return false;
        }
        self::$db->prepared_query("
            INSERT INTO donor_forum_usernames (UserID, Prefix) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE Prefix = ?
            ", $this->id(), $prefix, $prefix
        );
        $this->flush();
        return true;
    }

    public function setForumSuffix(string $suffix): bool {
        if (!$this->hasForum()) {
            return false;
        }
        self::$db->prepared_query("
            INSERT INTO donor_forum_usernames (UserID, Suffix) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE Suffix = ?
            ", $this->id(), $suffix, $suffix
        );
        $this->flush();
        return true;
    }

    public function setForumUseComma(bool $use): bool {
        if (!$this->hasForum()) {
            return false;
        }
        self::$db->prepared_query("
            INSERT INTO donor_forum_usernames (UserID, UseComma) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE UseComma = ?
            ", $this->id(), (int)$use, (int)$use
        );
        $this->flush();
        return true;
    }

    public function addDonorStatus(): int {
        if ($this->isDonor()) {
            return 0;
        }
        $affected = (new Privilege($this->user))->addSecondaryClass('Donor');
        $this->isDonor = ($affected === 1);
        return $affected;
    }

    public function removeDonorStatus(): int {
        self::$db->begin_transaction();
        $affected = (new Privilege($this->user))->removeSecondaryClass('Donor');
        self::$db->prepared_query('
            UPDATE users_donor_ranks SET
                donor_rank  = 0,
                SpecialRank = 0,
                TotalRank   = 0
            WHERE UserID = ?
            ', $this->id()
        );
        self::$db->commit();
        $this->flush();
        $this->user->flush();
        $this->isDonor = false;
        return $affected;
    }

    /**
     * You should never need to use this in production: it will wipe all traces
     * of donations from a user. Note that it will not perform any adjustments
     * to the number of invites that a user may have received through donations.
     */
    public function remove(): int {
        $this->removeDonorStatus();
        self::$db->prepared_query("
            DELETE d, dr, dfu, udr
            FROM donations                  d
            LEFT JOIN donor_rewards         dr  USING (UserID)
            LEFT JOIN donor_forum_usernames dfu USING (UserID)
            LEFT JOIN users_donor_ranks     udr USING (UserID)
            WHERE d.UserID = ?
            ", $this->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        $this->user()->flush();
        return $affected;
    }
}
