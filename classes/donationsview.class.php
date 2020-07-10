<?php

class DonationsView {
    public static function render_mod_donations(int $UserID) {
        $donorMan = new Gazelle\Manager\Donation;
        echo G::$Twig->render('donation/admin-panel.twig', [
            'rank' => $donorMan->rank($UserID),
            'special_rank' => $donorMan->specialRank($UserID),
            'total_rank' => $donorMan->totalRank($UserID),
        ]);
    }

    public static function render_donor_stats($UserID) {
        $OwnProfile = G::$LoggedUser['ID'] == $UserID;
        $donorMan = new Gazelle\Manager\Donation;
        if (check_perms("users_mod") || $OwnProfile || $donorMan->isVisible($UserID)) {
?>
            <div class="box box_info box_userinfo_donor_stats">
                <div class="head colhead_dark">Donor Statistics</div>
                <ul class="stats nobullet">
<?php
            if ($donorMan->isDonor($UserID)) {
                if (check_perms('users_mod') || $OwnProfile) { ?>
                    <li>
                        Total donor points: <?= $donorMan->totalRank($UserID) ?>
                    </li>
<?php           } ?>
                    <li>
                        Current donor rank: <?=self::render_rank($donorMan->rank($UserID), $donorMan->specialRank($UserID), true)?>
                    </li>
                    <li>
                        Leaderboard position: <?=$donorMan->leaderboardRank($UserID)?>
                    </li>
                    <li>
                        Last donated: <?=time_diff($donorMan->lastDonation($UserID))?>
                    </li>
                    <li>
                        Rank expires: <?=($donorMan->rankExpiry($UserID))?>
                    </li>
<?php            } else { ?>
                    <li>
<?php               if ($OwnProfile) { ?>
                        You haven't donated.
<?php               } else { ?>
                        This user hasn't donated.
<?php               } ?>
                    </li>
<?php            } ?>
                </ul>
            </div>
<?php
        }
    }

    public static function render_profile_rewards($EnabledRewards, $ProfileRewards) {
        for ($i = 1; $i <= 4; $i++) {
            if ($EnabledRewards['HasProfileInfo' . $i] && $ProfileRewards['ProfileInfo' . $i]) {
?>
            <div class="box">
                <div class="head" style="height: 13px;">
                    <span style="float: left;"><?=!empty($ProfileRewards['ProfileInfoTitle' . $i]) ? display_str($ProfileRewards['ProfileInfoTitle' . $i]) : "Extra Profile " . ($i + 1)?></span>
                    <span style="float: right;"><a href="#" onclick="$('#profilediv_<?=$i?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>
                </div>
                <div class="pad profileinfo" id="profilediv_<?=$i?>">
<?php                    echo Text::full_format($ProfileRewards['ProfileInfo' . $i]); ?>
                </div>
            </div>
<?php
            }
        }
    }

    public static function render_donation_history($DonationHistory) {
        if (empty($DonationHistory)) {
            return;
        }
?>
        <div class="box box2" id="donation_history_box">
            <div class="head">
                Donation History <a href="#" onclick="$('#donation_history').gtoggle(); return false;" class="brackets">View</a>
            </div>
<?php        $Row = 'b'; ?>
            <div class="hidden" id="donation_history">
                <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                    <tbody>
                    <tr class="colhead_dark">
                        <td>
                            <strong>Source</strong>
                        </td>
                        <td>
                            <strong>Date</strong>
                        </td>
                        <td>
                            <strong>Amount (EUR)</strong>
                        </td>
                        <td>
                            <strong>Added Points</strong>
                        </td>
                        <td>
                            <strong>Total Points</strong>
                        </td>
                        <td style="width: 30%;">
                            <strong>Reason</strong>
                        </td>
                    </tr>
<?php           foreach ($DonationHistory as $Donation) { ?>
                    <tr class="row<?=$Row?>">
                        <td>
                            <?=display_str($Donation['Source'])?> (<?=Users::format_username($Donation['AddedBy'])?>)
                        </td>
                        <td>
                            <?=$Donation['Time']?>
                        </td>
                        <td>
                            <?=$Donation['Amount']?>
                        </td>
                        <td>
                            <?=$Donation['Rank']?>
                        </td>
                        <td>
                            <?=$Donation['TotalRank']?>
                        </td>
                        <td>
                            <?=display_str($Donation['Reason'])?>
                        </td>
                    </tr>
<?php
                    $Row = $Row === 'b' ? 'a' : 'b';
                }
?>
                    </tbody>
                </table>
            </div>
        </div>
<?php
    }

    public static function render_rank($Rank, $SpecialRank, $ShowOverflow = false) {
        if ($SpecialRank == 3) {
            $Display = '∞ [Diamond]';
        } else {
            $CurrentRank = $Rank >= MAX_RANK ? MAX_RANK : $Rank;
            $Overflow = $Rank - $CurrentRank;
            $Display = $CurrentRank;
            if ($Display == 5 || $Display == 6) {
                $Display--;
            }
            if ($ShowOverflow && $Overflow) {
                $Display .= " (+$Overflow)";
            }
            if ($Rank >= 6) {
                $Display .= ' [Gold]';
            } elseif ($Rank >= 4) {
                $Display .= ' [Silver]';
            } elseif ($Rank >= 3) {
                $Display .= ' [Bronze]';
            } elseif ($Rank >= 2) {
                $Display .= ' [Copper]';
            } elseif ($Rank >= 1) {
                $Display .= ' [Red]';
            }
        }
        echo $Display;
    }
}
