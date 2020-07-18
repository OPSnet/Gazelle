<?php

namespace Gazelle\Manager;

class Donation extends \Gazelle\Base {

    protected $twig;

    // TODO: use dependency injection
    public function twig(\Twig\Environment $twig) {
        $this->twig = $twig;
    }

    public function moderatorAdjust(int $UserID, int $Rank, int $TotalRank, string $Reason, int $who) {
        $this->donate($UserID, [
            "Source" => "Modify Values",
            "Rank" => (int)$Rank,
            "TotalRank" => (int)$TotalRank,
            "SendPM" => false,
            "Reason" => $Reason,
            "Who"    => $who,
        ]);
    }

    public function moderatorDonate(int $UserID, string $amount, string $Currency, string $Reason, int $who) {
        $this->donate($UserID, [
            "Source" => 'Add Points',
            "Amount" => $amount,
            "Currency" => $Currency,
            "SendPM" => true,
            "Reason" => $Reason,
            "Who"    => $who,
        ]);
    }

    public function regularDonate(int $UserID, string $DonationAmount, string $Source, string $Reason, $Currency = "EUR") {
        $this->donate($UserID, [
            "Source" => $Source,
            "Amount" => $DonationAmount,
            "Currency" => $Currency,
            "SendPM" => true,
            "Reason" => $Reason,
            "Who"    => $UserID,
        ]);
    }

    public function donate(int $UserID, array $Args) {
        $UserID = $UserID;
        $QueryID = $this->db->get_query_id();

        $this->cache->InternalCache = false;

        if (!isset($Args['Amount'])) {
            $xbtAmount = 0.0;
            $fiatAmount = 0.0;
        } else {
            $XBT = new \Gazelle\Manager\XBT;
            $forexRate = $XBT->latestRate('EUR');
            switch ($Args['Currency'] == 'XBT') {
                case 'XBT':
                    $xbtAmount = $Args['Amount'];
                    $fiatAmount = $Args['Amount'] * $forexRate;
                    break;
                case 'EUR':
                    $xbtAmount = $Args['Amount'] / $forexRate;
                    $fiatAmount = $Args['Amount'];
                    break;
                default:
                    $xbtAmount = $XBT->fiat2xbt($Args['Amount'], $Args['Currency']);
                    $fiatAmount = $xbtAmount * $forexRate;
                    break;
            }
        }

        // A rank is acquired for a configured DONOR_RANK_PRICE.
        // Multiple ranks can be acquired at once, but the current rank cannot exceed MAX_EXTRA_RANK
        // The entire number of multiple ranks purchased at donation time are credited to Total ranks.
        // Total ranks acquired (all time) unlock Special ranks.
        $rankDelta = $Args['Rank'] ?? floor($fiatAmount / DONOR_RANK_PRICE);
        $totalDelta = $Args['TotalRank'] ?? $rankDelta;

        $this->db->prepared_query('
            INSERT INTO users_donor_ranks
                   (UserID, Rank, TotalRank)
            VALUES (?,      ?,    ?)
            ON DUPLICATE KEY UPDATE
                Rank               = coalesce(Rank, 0) + ?,
                TotalRank          = coalesce(TotalRank, 0) + ?,
                DonationTime       = now(),
                RankExpirationTime = now()
            ', $UserID,
                $rankDelta, $totalDelta,
                $rankDelta, $totalDelta
        );

        // Fetch their current donor rank after update
        [$Rank, $TotalRank, $SpecialRank, $previousInvites] = $this->db->row('
            SELECT Rank, TotalRank, SpecialRank, InvitesReceivedRank
            FROM users_donor_ranks
            WHERE UserID = ?
            ', $UserID
        );

        // They have been undonored
        if ($xbtAmount == 0.0 && $Rank == 0 && $TotalRank == 0) {
            $this->removeDonorStatus($UserID);
            $this->cache->deleteMulti(["user_info_$UserID", "user_info_heavy_$UserID", "donor_info_$UserID"]);
            return;
        }

        // Assign them to the Donor secondary class if it hasn't already been done
        $inviteForNewDonor = $xbtAmount > 0 ? DONOR_FIRST_INVITE_COUNT * $this->addDonorStatus($UserID) : 0;

        // Now that their rank and total rank has been set, we can calculate their special rank and invites
        $column = [];
        $args = [];
        $newSpecial = $this->calculateSpecialRank($UserID, $TotalRank);
        if ($newSpecial != $SpecialRank) {
            $column[] = 'SpecialRank = ?';
            $args[] = $newSpecial;
        }

        // One invite given per two ranks gained, up to a certain limit
        $newInvites = min(MAX_RANK, floor($Rank / 2)) - $previousInvites;
        if ($newInvites) {
            $column[] = 'InvitesReceivedRank = coalesce(InvitesReceivedRank, 0) + ?';
            $args[] = $newInvites;
        }
        if ($column) {
            $sql = 'UPDATE users_donor_ranks SET '
                . implode(', ', $column)
                . ' WHERE UserID = ?';
            $args[] = $UserID;
            $this->db->prepared_query($sql, ...$args);
        }

        if ($inviteForNewDonor || $newInvites) {
            $this->db->prepared_query('
                UPDATE users_main
                SET Invites = Invites + ?
                WHERE ID = ?
                ', $inviteForNewDonor + $newInvites, $UserID
            );
        }

        // Send them a thank you PM
        if ($Args['SendPM']) {
            \Misc::send_pm(
                $UserID,
                0,
                'Your contribution has been received and credited. Thank you!',
                $this->messageBody($Args['Source'], $Args['Currency'], $Args['Amount'], $rankDelta, $Rank)
            );
        }

        // Add this donation to our history, with the reason for giving invites
        $reason = trim($Args['Reason'] . " invites new=$inviteForNewDonor prev=$previousInvites given=$newInvites");
        $this->db->prepared_query('
            INSERT INTO donations
                   (UserID, Amount, Source, Reason, Currency, AddedBy, Rank, TotalRank, xbt)
            VALUES (?,      ?,      ?,      ?,      ?,        ?,       ?,    ?,         ?)
            ', $UserID, round($fiatAmount, 2), $Args['Source'], $reason, $Args['Currency'] ?? 'XZZ',
                $Args['Who'], $rankDelta, $TotalRank, $xbtAmount
        );

        // Clear their user cache keys because the users_info values has been modified
        $this->cache->deleteMulti(["user_info_$UserID", "user_info_heavy_$UserID", "donor_info_$UserID",
            'donations_month_3', 'donations_month_12']);
        $this->db->set_query_id($QueryID);
    }

    protected function calculateSpecialRank(int $UserID, int $TotalRank) {
        if ($TotalRank < 10) {
            $SpecialRank = 0;
        }

        if ($SpecialRank < 1 && $TotalRank >= 10) {
            \Misc::send_pm( $UserID, 0,
                "You have Reached Special Donor Rank #1! You've Earned: One User Pick. Details Inside.",
                $this->twig->render('donation/special-rank-1.twig', [
                   'forum_url'   => site_url() . 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'site_name'   => SITE_NAME,
                   'staffpm_url' => site_url() . 'staffpm.php',
                ])
            );
            $SpecialRank = 1;
        }

        if ($SpecialRank < 2 && $TotalRank >= 20) {
            \Misc::send_pm($UserID, 0,
                "You have Reached Special Donor Rank #2! You've Earned: The Double-Avatar. Details Inside.",
                $this->twig->render('donation/special-rank-2.twig', [
                   'forum_url' => site_url() . 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'site_name' => SITE_NAME,
                ])
            );
            $SpecialRank = 2;
        }

        if ($SpecialRank < 3 && $TotalRank >= 50) {
            \Misc::send_pm($UserID, 0,
                "You have Reached Special Donor Rank #3! You've Earned: Diamond Rank. Details Inside.",
                $this->twig->render('donation/special-rank-3.twig', [
                   'forum_url'      => site_url() . 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'forum_gold_url' => site_url() . 'forums.php?action=viewthread&threadid=178640&postid=4839789#post4839789',
                   'site_name'      => SITE_NAME,
                ])
            );
            $SpecialRank = 3;
        }
        return $SpecialRank;
    }

    protected function addDonorStatus(int $UserID): int {
        if (($class = $this->db->scalar('SELECT ID FROM permissions WHERE Name = ?', 'Donor')) !== null) {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_levels
                       (UserID, PermissionID)
                VALUES (?,      ?)
                ', $UserID, $class
            );
            return $this->db->affected_rows();
        }
        return 0;
    }

    protected function removeDonorStatus(int $UserID): int {
        $class = $this->db->scalar('SELECT ID FROM permissions WHERE Name = ?', 'Donor');
        if ($class) {
            $this->db->prepared_query('
                DELETE FROM users_levels
                WHERE UserID = ?
                    AND PermissionID = ?
                ', $UserID, $class
            );
        }
        $this->db->prepared_query('
            UPDATE users_donor_ranks SET
                SpecialRank = 0
            WHERE UserID = ?
            ', $UserID
        );
        return $this->db->affected_rows();
    }

    protected function toggleHidden(int $userId, string $state): int {
        $this->db->prepared_query("
            INSERT INTO users_donor_ranks
                   (UserID, Hidden)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE
                Hidden = ?
            ", $userId, $state, $state
        );
        return $this->db->affected_rows();
    }

    public function hide(int $userId): int {
        return $this->toggleHidden($userId, '1');
    }

    public function show(int $userId): int {
        return $this->toggleHidden($userId, '0');
    }

    public function hasForumAccess($UserID) {
        return $this->rank($UserID) >= DONOR_FORUM_RANK || $this->specialRank($UserID) >= MAX_SPECIAL_RANK;
    }

    /**
     * Put all the common donor info in the same cache key to save some cache calls
     */
    protected function info($UserID) {
        // Our cache class should prevent identical memcached requests
        $DonorInfo = $this->cache->get_value("donor_info_$UserID");
        if ($DonorInfo === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT
                    Rank,
                    SpecialRank,
                    TotalRank,
                    DonationTime,
                    RankExpirationTime + INTERVAL 766 HOUR
                FROM users_donor_ranks
                WHERE UserID = ?
                ', $UserID
            );
            // 2 hours less than 32 days to account for schedule run times
            if ($this->db->has_results()) {
                [$Rank, $SpecialRank, $TotalRank, $DonationTime, $ExpireTime]
                    = $this->db->next_record(MYSQLI_NUM, false);
                if ($DonationTime === null) {
                    $DonationTime = 0;
                }
                if ($ExpireTime === null) {
                    $ExpireTime = 0;
                }
            } else {
                $Rank = $SpecialRank = $TotalRank = $DonationTime = $ExpireTime = 0;
            }
            if (\Permissions::is_mod($UserID)) {
                $Rank = MAX_EXTRA_RANK;
                $SpecialRank = MAX_SPECIAL_RANK;
            }
            $this->db->prepared_query('
                SELECT
                    IconMouseOverText,
                    AvatarMouseOverText,
                    CustomIcon,
                    CustomIconLink,
                    SecondAvatar
                FROM donor_rewards
                WHERE UserID = ?
                ', $UserID
            );
            $Rewards = $this->db->next_record(MYSQLI_ASSOC);
            $this->db->set_query_id($QueryID);

            $DonorInfo = [
                'Rank' => (int)$Rank,
                'SRank' => (int)$SpecialRank,
                'TotRank' => (int)$TotalRank,
                'Time' => $DonationTime,
                'ExpireTime' => $ExpireTime,
                'Rewards' => $Rewards
            ];
            $this->cache->cache_value("donor_info_$UserID", $DonorInfo, 86400);
        }
        return $DonorInfo;
    }

    public function rank($UserID) {
        return $this->info($UserID)['Rank'];
    }

    public function specialRank($UserID) {
        return $this->info($UserID)['SRank'];
    }

    public function totalRank($UserID) {
        return $this->info($UserID)['TotRank'];
    }

    public function lastDonation($UserID) {
        return $this->info($UserID)['Time'];
    }

    public function personalCollages($UserID) {
        $DonorInfo = $this->info($UserID);
        if ($DonorInfo['SRank'] == MAX_SPECIAL_RANK) {
            $Collages = 5;
        } else {
            $Collages = min($DonorInfo['Rank'], 5); // One extra collage per donor rank up to 5
        }
        return $Collages;
    }

    public function titles($UserID) {
        $Results = $this->cache->get_value("donor_title_$UserID");
        if ($Results === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT Prefix, Suffix, UseComma
                FROM donor_forum_usernames
                WHERE UserID = ?
                ', $UserID
            );
            $Results = $this->db->next_record();
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value("donor_title_$UserID", $Results, 0);
        }
        return $Results;
    }

    public function enabledRewards($UserID) {
        $Rewards = [];
        $Rank = $this->rank($UserID);
        $SpecialRank = $this->specialRank($UserID);
        $HasAll = $SpecialRank == 3;

        $Rewards = [
            'HasAvatarMouseOverText' => false,
            'HasCustomDonorIcon' => false,
            'HasDonorForum' => false,
            'HasDonorIconLink' => false,
            'HasDonorIconMouseOverText' => false,
            'HasProfileInfo1' => false,
            'HasProfileInfo2' => false,
            'HasProfileInfo3' => false,
            'HasProfileInfo4' => false,
            'HasSecondAvatar' => false
        ];

        if ($Rank >= 2 || $HasAll) {
            $Rewards["HasDonorIconMouseOverText"] = true;
            $Rewards["HasProfileInfo1"] = true;
        }
        if ($Rank >= 3 || $HasAll) {
            $Rewards["HasAvatarMouseOverText"] = true;
            $Rewards["HasProfileInfo2"] = true;
        }
        if ($Rank >= 4 || $HasAll) {
            $Rewards["HasDonorIconLink"] = true;
            $Rewards["HasProfileInfo3"] = true;
        }
        if ($Rank >= MAX_RANK || $HasAll) {
            $Rewards["HasCustomDonorIcon"] = true;
            $Rewards["HasDonorForum"] = true;
            $Rewards["HasProfileInfo4"] = true;
        }
        if ($SpecialRank >= 2) {
            $Rewards["HasSecondAvatar"] = true;
        }
        return $Rewards;
    }

    public function rewards($UserID) {
        return $this->info($UserID)['Rewards'];
    }

    public function profileRewards($UserID) {
        $Results = $this->cache->get_value("donor_profile_rewards_$UserID");
        if ($Results === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT
                    ProfileInfo1,
                    ProfileInfoTitle1,
                    ProfileInfo2,
                    ProfileInfoTitle2,
                    ProfileInfo3,
                    ProfileInfoTitle3,
                    ProfileInfo4,
                    ProfileInfoTitle4
                FROM donor_rewards
                WHERE UserID = ?
                ', $UserID
            );
            $Results = $this->db->next_record();
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value("donor_profile_rewards_$UserID", $Results, 0);
        }
        return $Results;
    }

    public function updateReward($UserID) {
        // TODO: could this be rewritten to avoid accessing $_POST directly?
        $Rank = $this->rank($UserID);
        $SpecialRank = $this->specialRank($UserID);
        $HasAll = $SpecialRank == 3;
        $insert = [];
        $args = [];

        if ($Rank >= 2 || $HasAll) {
            if (isset($_POST['donor_icon_mouse_over_text'])) {
                $insert[] = "IconMouseOverText";
                $args[] = trim($_POST['donor_icon_mouse_over_text']);
            }
        }
        if ($Rank >= 3 || $HasAll) {
            if (isset($_POST['avatar_mouse_over_text'])) {
                $insert[] = "AvatarMouseOverText";
                $args[] = trim($_POST['avatar_mouse_over_text']);
            }
        }
        if ($Rank >= 4 || $HasAll) {
            if (isset($_POST['donor_icon_link'])) {
                $value = trim($_POST['donor_icon_link']);
                if (preg_match("/^".URL_REGEX."$/i", $value)) {
                    $insert[] = "CustomIconLink";
                    $args[] = $value;
                }
            }
        }
        if ($Rank >= MAX_RANK || $HasAll) {
            if (isset($_POST['donor_icon_custom_url'])) {
                $value = trim($_POST['donor_icon_custom_url']);
                if (preg_match("/^".IMAGE_REGEX."$/i", $value)) {
                    $insert[] = "CustomIcon";
                    $args[] = $value;
                }
            }
            $this->updateTitle($UserID, $_POST['donor_title_prefix'], $_POST['donor_title_suffix'], $_POST['donor_title_comma']);
        }

        for ($i = 1; $i < min(MAX_RANK, $Rank); $i++) {
            if (isset($_POST["profile_title_" . $i]) && isset($_POST["profile_info_" . $i])) {
                $insert[] = "ProfileInfoTitle" . $i;
                $insert[] = "ProfileInfo" . $i;
                $args[] = trim($_POST["profile_title_" . $i]);
                $args[] = trim($_POST["profile_info_" . $i]);
            }
        }
        if ($SpecialRank >= 2) {
            if (isset($_POST['second_avatar'])) {
                $value = trim($_POST['second_avatar']);
                if (preg_match("/^".IMAGE_REGEX."$/i", $value)) {
                    $insert[] = "SecondAvatar";
                    $args[] = $value;
                }
            }
        }
        if (count($insert) > 0) {
            $this->db->prepared_query("
                INSERT INTO donor_rewards
                       (UserID, " . implode(', ', $insert) . ")
                VALUES (?, " . placeholders($insert) . ")
                ON DUPLICATE KEY UPDATE
                " . implode(', ', array_map(function ($c) { return "$c = ?";}, $insert)),
                $UserID, ...array_merge($args, $args)
            );
        }
        $this->cache->deleteMulti(["donor_profile_rewards_$UserID", "donor_info_$UserID"]);
    }

    // TODO: make $UseComma more sane
    public function updateTitle($UserID, $Prefix, $Suffix, $UseComma) {
        $QueryID = $this->db->get_query_id();
        $Prefix = trim($Prefix);
        $Suffix = trim($Suffix);
        $UseComma = empty($UseComma) ? true : false;
        $this->db->prepared_query('
            INSERT INTO donor_forum_usernames
                   (UserID, Prefix, Suffix, UseComma)
            VALUES (?,      ?,      ?,      ?)
            ON DUPLICATE KEY UPDATE
                Prefix = ?, Suffix = ?, UseComma = ?
            ', $UserID, $Prefix, $Suffix, $UseComma !== null ? 1 : 0,
                $Prefix, $Suffix, $UseComma !== null ? 1 : 0
        );
        $this->cache->delete_value("donor_title_$UserID");
        $this->db->set_query_id($QueryID);
    }

    public function history(int $UserID) {
        if ($UserID < 1) {
            error(404);
        }
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query('
            SELECT Amount, Time, Currency, Reason, Source, AddedBy, Rank, TotalRank
            FROM donations
            WHERE UserID = ?
            ORDER BY Time DESC
            ', $UserID
        );
        $DonationHistory = $this->db->to_array(false, MYSQLI_ASSOC, false);
        $this->db->set_query_id($QueryID);
        return $DonationHistory;
    }

    public function rankExpiry($UserID) {
        $DonorInfo = $this->info($UserID);
        if ($DonorInfo['SRank'] == MAX_SPECIAL_RANK || $DonorInfo['Rank'] == 1) {
            $Return = 'Never';
        } elseif ($DonorInfo['ExpireTime']) {
            $ExpireTime = strtotime($DonorInfo['ExpireTime']);
            if ($ExpireTime - time() < 60) {
                $Return = 'Soon';
            } else {
                $Expiration = time_diff($ExpireTime); // 32 days
                $Return = "in $Expiration";
            }
        } else {
            $Return = '';
        }
        return $Return;
    }

    public function leaderboardRank(int $UserID): int {
        $this->db->prepared_query("SET @RowNum := 0");
        $Position = $this->db->scalar("
            SELECT Position
            FROM (
                SELECT d.UserID, @RowNum := @RowNum + 1 AS Position
                FROM users_donor_ranks AS d
                ORDER BY TotalRank DESC
            ) l
            WHERE UserID = ?
            ", $UserID
        );
        return $Position ?? 0;
    }

    public function isDonor(int $userId) {
        return $this->rank($userId) > 0;
    }

    public function isVisible(int $userId): int {
        return is_null($this->db->scalar("
            SELECT Hidden
            FROM users_donor_ranks
            WHERE Hidden = '1'
                AND UserID = ?
            ", $userId
        ));
    }

    protected function messageBody(string $Source, string $Currency, string $amount, int $ReceivedRank, int $CurrentRank) {
        if ($Currency != 'XBT') {
            $amount = number_format($amount, 2);
        }
        if ($CurrentRank >= MAX_RANK) {
            $CurrentRank = MAX_RANK - 1;
        } elseif ($CurrentRank == 5) {
            $CurrentRank = 4;
        }
        return $this->twig->render('donation/donation-pm.twig', [
            'amount' => $amount,
            'cc'     => $Currency,
            'points' => $ReceivedRank,
            's'      => $ReceivedRank == 1 ? '' : 's',
            'rank'   => $CurrentRank,
            'staffpm_url' => site_url() . 'staffpm.php',
        ]);
    }

    public function totalMonth(int $month) {
        if (($donations = $this->cache->get_value("donations_month_$month")) === false) {
            $donations = $this->db->scalar("
                SELECT sum(xbt)
                FROM donations
                WHERE time >= CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') as DATE) - INTERVAL ? MONTH
                ", $month - 1
            );
            $this->cache->cache_value("donations_month_$month", $donations, 3600 * 36);
        }
        return $donations;
    }

    public function expireRanks(): int {
        $this->db->prepared_query("
            SELECT UserID
            FROM users_donor_ranks
            WHERE Rank > 1
                AND SpecialRank != 3
                AND RankExpirationTime < NOW() - INTERVAL 766 HOUR
        "); // 2 hours less than 32 days to account for schedule run times
        $userIds = [];
        while ([$id] = $this->db->next_record()) {
            $this->cache->deleteMulti(["donor_info_$id", "donor_title_$id", "donor_profile_rewards_$id"]);
            $userIds[] = $id;
        }
        if ($userIds) {
            $this->db->prepared_query("
                UPDATE users_donor_ranks SET
                    Rank = Rank - 1,
                    RankExpirationTime = now()
                WHERE Rank > 1
                    AND UserID IN (" . placeholders($userIds) . ")
                ", ...$userIds
            );
        }
        return count($userIds);
    }
}
