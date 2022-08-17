<?php
class Users {
    /**
     * Returns a username string for display
     *
     * @param int|string $UserID
     * @param boolean $Badges whether or not badges (donor, warned, enabled) should be shown
     * @param boolean $IsWarned -- TODO: Why the fuck do we need this?
     * @param boolean $IsEnabled -- TODO: Why the fuck do we need this?
     * @param boolean $Class whether or not to show the class
     * @param boolean $Title whether or not to show the title
     * @param boolean $IsDonorForum for displaying donor forum honorific prefixes and suffixes
     * @return string HTML formatted username
     */
    public static function format_username($UserID, $Badges = false, $IsWarned = true, $IsEnabled = true, $Class = false, $Title = false, $IsDonorForum = false) {
        if ($UserID == 0) {
            return 'System';
        }
        $userMan = new Gazelle\Manager\User;
        $user = $userMan->findById($UserID);
        if (is_null($user)) {
            return "Unknown [$UserID]";
        }

        global $Viewer; // FIXME this is wrong
        $imgProxy =  (new \Gazelle\Util\ImageProxy)->setViewer($Viewer);
        $Classes = $userMan->classList();
        if ($user->primaryClass() < $Classes[MOD]['Level']) {
            $OverrideParanoia = $Viewer->permitted('users_override_paranoia', $user->primaryClass());
        } else {
            // Don't override paranoia for mods who don't want to show their donor heart
            $OverrideParanoia = false;
        }

        $Username = $user->username();
        if ($IsDonorForum) {
            [$Prefix, $Suffix, $HasComma] = $user->donorTitles();
            $Username = "$Prefix $Username" . ($HasComma ? ', ' : ' ') . $Suffix;
        }

        if ($Title) {
            $Str = "<strong><a href=\"user.php?id=$UserID\">$Username</a></strong>";
        } else {
            $Str = "<a href=\"user.php?id=$UserID\">$Username</a>";
        }

        if ($Badges) {
            $DonorRank = $user->donorRank();
            if ($DonorRank == 0 && (new \Gazelle\User\Privilege($user))->isDonor()) {
                $DonorRank = 1;
            }
            if ($DonorRank > 0 && ($OverrideParanoia || $user->propertyVisible($Viewer, 'hide_donor_heart'))) {
                $EnabledRewards = $user->enabledDonorRewards();
                $DonorRewards = $user->donorRewards();
                $IconText = ($EnabledRewards['HasDonorIconMouseOverText'] && !empty($DonorRewards['IconMouseOverText']))
                    ? display_str($DonorRewards['IconMouseOverText']) : 'Donor';
                $IconLink = ($EnabledRewards['HasDonorIconLink'] && !empty($DonorRewards['CustomIconLink']))
                    ? display_str($DonorRewards['CustomIconLink']) : 'donate.php';
                if ($EnabledRewards['HasCustomDonorIcon'] && !empty($DonorRewards['CustomIcon'])) {
                    $IconImage = $imgProxy->process($DonorRewards['CustomIcon'], 'donoricon', $UserID);
                } else {
                    if ($user->specialDonorRank() === MAX_SPECIAL_RANK) {
                        $DonorHeart = 6;
                    } elseif ($DonorRank === 5) {
                        $DonorHeart = 4; // Two points between rank 4 and 5
                    } elseif ($DonorRank >= MAX_RANK) {
                        $DonorHeart = 5;
                    } else {
                        $DonorHeart = $DonorRank;
                    }
                    if ($DonorHeart === 1) {
                        $IconImage = STATIC_SERVER . '/common/symbols/donor.png';
                    } else {
                        $IconImage = STATIC_SERVER . "/common/symbols/donor_{$DonorHeart}.png";
                    }
                }
                $Str .= "<a target=\"_blank\" href=\"$IconLink\"><img class=\"donor_icon tooltip\" src=\"$IconImage\" alt=\"$IconText\" title=\"$IconText\" /></a>";
            }
        }

        $Str .= ($IsWarned && $user->isWarned()) ? '<a href="wiki.php?action=article&amp;name=warnings"'
            . '><img src="'.STATIC_SERVER.'/common/symbols/warned.png" alt="Warned" title="Warned'
            . ($Viewer->id() == $UserID ? ' - Expires ' . date('Y-m-d H:i', strtotime($user->warningExpiry())) : '')
            . '" class="tooltip" /></a>' : '';
        $Str .= ($IsEnabled && $user->isDisabled())
            ? '<a href="rules.php"><img src="'.STATIC_SERVER.'/common/symbols/disabled.png" alt="Banned" title="Disabled" class="tooltip" /></a>'
            : '';

        if ($Badges) {
            $badgeList = [];
            $privilege = new Gazelle\User\Privilege($user);
            foreach ($privilege->badgeList() as $badge => $name) {
                $badgeList[] = '<span class="tooltip secondary_class" title="' . $name . '">' . $badge . '</span>';
            }
            if ($badgeList) {
                $Str .= '&nbsp;'.implode('&nbsp;', $badgeList);
            }
        }

        if ($Class) {
            $userClass = $userMan->userclassName($user->primaryClass());
            if ($Title) {
                $Str .= " <strong>($userClass)</strong>";
            } else {
                $Str .= " ($userClass)";
            }
        }

        if ($Title) {
            // Image proxy CTs
            $userTitle = $user->title();
            if ($Viewer->permitted('site_proxy_images') && !empty($userTitle)) {
                $userTitle = preg_replace_callback('/src=("?)(http.+?)(["\s>])/',
                    function($Matches) use ($imgProxy) {
                        return 'src=' . $Matches[1] . $imgProxy->process($Matches[2]) . $Matches[3];
                    }, $userTitle
                );
            }
            if ($userTitle) {
                $Str .= ' <span class="user_title">(' . $userTitle . ')</span>';
            }
        }
        return $Str;
    }
}
