<?php

namespace Gazelle\Manager;

class Donation extends \Gazelle\Base {

    protected $twig;

    public function twig(\Twig\Environment $twig) {
        $this->twig = $twig;
    }

    public function moderatorAdjust(\Gazelle\User $user, int $Rank, int $TotalRank, string $Reason, int $who) {
        $this->donate($user, [
            "Source" => "Modify Values",
            "Rank" => (int)$Rank,
            "TotalRank" => (int)$TotalRank,
            "SendPM" => false,
            "Reason" => $Reason,
            "Who"    => $who,
        ]);
    }

    public function moderatorDonate(\Gazelle\User $user, string $amount, string $Currency, string $Reason, int $who) {
        $this->donate($user, [
            "Source" => 'Add Points',
            "Amount" => $amount,
            "Currency" => $Currency,
            "SendPM" => true,
            "Reason" => $Reason,
            "Who"    => $who,
        ]);
    }

    public function regularDonate(\Gazelle\User $user, string $DonationAmount, string $Source, string $Reason, $Currency = "EUR") {
        $this->donate($user, [
            "Source" => $Source,
            "Amount" => $DonationAmount,
            "Currency" => $Currency,
            "SendPM" => true,
            "Reason" => $Reason,
            "Who"    => $user->id(),
        ]);
    }

    public function donate(\Gazelle\User $user, array $Args) {
        $QueryID = $this->db->get_query_id();
        if (!isset($Args['Amount'])) {
            $xbtAmount = 0.0;
            $fiatAmount = 0.0;
        } else {
            $XBT = new XBT;
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
        $UserID = $user->id();

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
            $this->cache->deleteMulti(["u_$UserID", "donor_info_$UserID"]);
            return;
        }

        // Assign them to the Donor secondary class if it hasn't already been done
        $inviteForNewDonor = $xbtAmount > 0 ? DONOR_FIRST_INVITE_COUNT * $this->addDonorStatus($UserID) : 0;

        // Now that their rank and total rank has been set, we can calculate their special rank and invites
        $column = [];
        $args = [];
        $newSpecial = $this->calculateSpecialRank($user, $TotalRank);
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
            (new \Gazelle\Manager\User)->sendPM($UserID, 0,
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
        $this->cache->deleteMulti(["u_$UserID", "donor_info_$UserID",
            'donations_month_3', 'donations_month_12']);
        $this->db->set_query_id($QueryID);
    }

    protected function calculateSpecialRank(\Gazelle\User $user, int $TotalRank) {
        $SpecialRank = $user->specialDonorRank();
        $UserID = $user->id();
        if ($TotalRank < 10) {
            $SpecialRank = 0;
        }

        if ($SpecialRank < 1 && $TotalRank >= 10) {
            (new \Gazelle\Manager\User)->sendPM($UserID, 0,
                "You have Reached Special Donor Rank #1! You've Earned: One User Pick. Details Inside.",
                $this->twig->render('donation/special-rank-1.twig', [
                   'forum_url'   => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'staffpm_url' => 'staffpm.php',
                ])
            );
            $SpecialRank = 1;
        }

        if ($SpecialRank < 2 && $TotalRank >= 20) {
            (new \Gazelle\Manager\User)->sendPM($UserID, 0,
                "You have Reached Special Donor Rank #2! You've Earned: The Double-Avatar. Details Inside.",
                $this->twig->render('donation/special-rank-2.twig', [
                   'forum_url' => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                ])
            );
            $SpecialRank = 2;
        }

        if ($SpecialRank < 3 && $TotalRank >= 50) {
            (new \Gazelle\Manager\User)->sendPM($UserID, 0,
                "You have Reached Special Donor Rank #3! You've Earned: Diamond Rank. Details Inside.",
                $this->twig->render('donation/special-rank-3.twig', [
                   'forum_url'      => 'forums.php?action=viewthread&threadid=178640&postid=4839790#post4839790',
                   'forum_gold_url' => 'forums.php?action=viewthread&threadid=178640&postid=4839789#post4839789',
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

    public function hasForumAccess(\Gazelle\User $user) {
        return $user->donorRank() >= DONOR_FORUM_RANK || $user->specialDonorRank() >= MAX_SPECIAL_RANK;
    }

    public function leaderboardRank(\Gazelle\User $user): int {
        $this->db->prepared_query("SET @RowNum := 0");
        $Position = $this->db->scalar("
            SELECT Position
            FROM (
                SELECT d.UserID, @RowNum := @RowNum + 1 AS Position
                FROM users_donor_ranks AS d
                ORDER BY TotalRank DESC
            ) l
            WHERE UserID = ?
            ", $user->id()
        );
        return $Position ?? 0;
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
            's'      => plural($ReceivedRank),
            'rank'   => $CurrentRank,
            'staffpm_url' => 'staffpm.php',
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
