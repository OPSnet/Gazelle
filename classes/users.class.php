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
        $donor = new Gazelle\User\Donor($user);

        global $Viewer; // FIXME this is wrong
        $Classes = $userMan->classList();
        if ($user->primaryClass() < $Classes[MOD]['Level']) {
            $OverrideParanoia = $Viewer->permitted('users_override_paranoia', $user->primaryClass());
        } else {
            // Don't override paranoia for mods who don't want to show their donor heart
            $OverrideParanoia = false;
        }

        $username = $donor->username($IsDonorForum);
        if ($Title) {
            $Str = "<strong><a href=\"user.php?id=$UserID\">$username</a></strong>";
        } else {
            $Str = "<a href=\"user.php?id=$UserID\">$username</a>";
        }
        if ($Badges) {
            $Str .= $donor->heart($Viewer);
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
                    function($Matches) {
                        return 'src=' . $Matches[1] . image_cache_encode($Matches[2]) . $Matches[3];
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
