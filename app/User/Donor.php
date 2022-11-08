<?php

namespace Gazelle\User;

class Donor extends \Gazelle\BaseUser {
    public function link(\Gazelle\User $viewer): string {
        if (!(new Privilege($this->user))->isDonor()) {
            return '';
        }
        $override = $this->user->isStaff() ? false : $viewer->permitted('users_override_paranoia');
        if (!$override && !$this->user->propertyVisible($viewer, 'hide_donor_heart')) {
            return '';
        }
        $enabled = $this->user->enabledDonorRewards();
        $rewards = $this->user->donorRewards();

        if ($enabled['HasCustomDonorIcon'] && !empty($rewards['CustomIcon'])) {
            $image = (new \Gazelle\Util\ImageProxy($viewer))
                ->process($rewards['CustomIcon'], 'donoricon', $this->user->id());
        } else {
            $rank = $this->user->donorRank();
            if ($rank == 0) {
                $rank = 1;
            }
            if ($this->user->specialDonorRank() === MAX_SPECIAL_RANK) {
                $heart = 6;
            } elseif ($rank === 5) {
                $heart = 4; // Two points between rank 4 and 5
            } elseif ($rank >= MAX_RANK) {
                $heart = 5;
            } else {
                $heart = $rank;
            }
            $image = STATIC_SERVER . '/common/symbols/' . ($heart === 1 ? 'donor.png' : "donor_{$heart}.png");
        }
        return '<a target="_blank" href="'
            . ($enabled['HasDonorIconLink'] ? display_str($rewards['CustomIconLink'] ?? 'donate.php') : 'donate.php')
            . '"><img class="donor_icon tooltip" src="' . $image . '" title="'
            . ($enabled['HasDonorIconMouseOverText'] ? display_str($rewards['IconMouseOverText'] ?? 'Donor') : 'Donor')
            . '" /></a>';
    }
}
