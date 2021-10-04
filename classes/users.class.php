<?php
class Users {
    /**
     * Get user info, is used for the current user and usernames all over the site.
     *
     * @param int $UserID The UserID to get info for
     * @return array with the following keys:
     *    int     ID
     *    string  Username
     *    int     PermissionID
     *    array   Paranoia - $Paranoia array sent to paranoia.class
     *    boolean Donor
     *    string  Warned - When their warning expires in international time format
     *    string  Avatar - URL
     *    boolean Enabled
     *    string  Title
     *    boolean Visible - If false, they don't show up on peer lists
     *    array   ExtraClasses - Secondary classes.
     *    int     EffectiveClass - the highest level of their main and secondary classes
     */
    public static function user_info($UserID) {
        global $Cache, $DB;
        $UserInfo = $Cache->get_value("user_info_$UserID");
        // the !isset($UserInfo['Paranoia']) can be removed after a transition period
        if (empty($UserInfo) || empty($UserInfo['ID']) || !isset($UserInfo['Paranoia']) || empty($UserInfo['Class'])) {
            $OldQueryID = $DB->get_query_id();

            $DB->prepared_query("
                SELECT
                    m.ID,
                    m.Username,
                    m.PermissionID,
                    m.Paranoia,
                    (donor.UserID IS NOT NULL) AS Donor,
                    i.Warned,
                    i.Avatar,
                    m.Enabled,
                    m.Title,
                    m.Visible,
                    la.Type AS LockedAccount,
                    GROUP_CONCAT(ul.PermissionID SEPARATOR ',') AS Levels
                FROM users_main AS m
                INNER JOIN users_info AS i ON (i.UserID = m.ID)
                LEFT JOIN locked_accounts AS la ON (la.UserID = m.ID)
                LEFT JOIN users_levels AS ul ON (ul.UserID = m.ID)
                LEFT JOIN users_levels AS donor ON (donor.UserID = m.ID
                    AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
                )
                WHERE m.ID = ?
                GROUP BY m.ID
                ", $UserID
            );

            $Classes = (new Gazelle\Manager\User)->classList();
            if (!$DB->has_results()) { // Deleted user, maybe?
                $UserInfo = [
                        'ID' => $UserID,
                        'Username' => '',
                        'PermissionID' => 0,
                        'Paranoia' => [],
                        'Donor' => false,
                        'Warned' => null,
                        'Avatar' => '',
                        'Enabled' => 0,
                        'Title' => '',
                        'Visible' => '1',
                        'Levels' => '',
                        'Class' => 0];
            } else {
                $UserInfo = $DB->next_record(MYSQLI_ASSOC, ['Paranoia', 'Title']);
                $UserInfo['Paranoia'] = unserialize_array($UserInfo['Paranoia']);
                if ($UserInfo['Paranoia'] === false) {
                    $UserInfo['Paranoia'] = [];
                }
                $UserInfo['Class'] = $Classes[$UserInfo['PermissionID']]['Level'];
            }

            if (isset($UserInfo['LockedAccount'])) {
                unset($UserInfo['LockedAccount']);
            }

            if (!empty($UserInfo['Levels'])) {
                $UserInfo['ExtraClasses'] = array_fill_keys(explode(',', $UserInfo['Levels']), 1);
            } else {
                $UserInfo['ExtraClasses'] = [];
            }
            unset($UserInfo['Levels']);
            $EffectiveClass = (int)$UserInfo['Class'];
            foreach ($UserInfo['ExtraClasses'] as $Class => $Val) {
                $EffectiveClass = max($EffectiveClass, (int)$Classes[$Class]['Level']);
            }
            $UserInfo['EffectiveClass'] = $EffectiveClass;

            $Cache->cache_value("user_info_$UserID", $UserInfo, 2592000);
            $DB->set_query_id($OldQueryID);
        }
        if (strtotime($UserInfo['Warned']) < time()) {
            $UserInfo['Warned'] = null;
            $Cache->cache_value("user_info_$UserID", $UserInfo, 2592000);
        }

        return $UserInfo;
    }

    /**
     * Gets the heavy user info
     * Only used for current user
     *
     * @param string $UserID The userid to get the information for
     * @return array fetched heavy info.
     *        Just read the goddamn code, I don't have time to comment this shit.
     */
    public static function user_heavy_info($UserID) {

        global $Cache, $DB;
        $HeavyInfo = $Cache->get_value("user_info_heavy_$UserID");
        if (empty($HeavyInfo)) {

            $QueryID = $DB->get_query_id();
            $DB->prepared_query('
                SELECT
                    m.Invites,
                    m.torrent_pass,
                    m.IP,
                    m.CustomPermissions,
                    m.can_leech AS CanLeech,
                    m.IRCKey,
                    i.Info,
                    i.AuthKey,
                    i.RatioWatchEnds,
                    i.RatioWatchDownload,
                    i.StyleID,
                    i.StyleURL,
                    i.DisableInvites,
                    i.DisablePosting,
                    i.DisableUpload,
                    i.DisablePoints,
                    i.DisableWiki,
                    i.DisableAvatar,
                    i.DisablePM,
                    i.DisableRequests,
                    i.DisableForums,
                    i.DisableIRC,
                    i.DisableTagging,
                    i.SiteOptions,
                    i.DownloadAlt,
                    i.RestrictedForums,
                    i.PermittedForums,
                    i.NavItems,
                    i.collages AS Collages,
                    uf.tokens AS FLTokens,
                    m.PermissionID,
                    CASE WHEN uha.UserID IS NULL THEN 1 ELSE 0 END AS AcceptFL
                FROM users_main AS m
                INNER JOIN users_info AS i ON (i.UserID = m.ID)
                INNER JOIN user_flt AS uf ON (uf.user_id = m.ID)
                LEFT JOIN user_has_attr AS uha ON (uha.UserID = m.ID)
                LEFT JOIN user_attr as ua ON (ua.ID = uha.UserAttrID AND ua.Name = ?)
                WHERE m.ID = ?
                ', 'no-fl-gifts', $UserID
            );
            $HeavyInfo = $DB->next_record(MYSQLI_ASSOC, ['CustomPermissions', 'SiteOptions']);
            if ($HeavyInfo['RatioWatchEnds'] == '') {
                $HeavyInfo['RatioWatchEnds'] = null;
            }

            $HeavyInfo['CustomPermissions'] = unserialize_array($HeavyInfo['CustomPermissions']);

            if (!empty($HeavyInfo['RestrictedForums'])) {
                $RestrictedForums = array_map('trim', explode(',', $HeavyInfo['RestrictedForums']));
            } else {
                $RestrictedForums = [];
            }
            unset($HeavyInfo['RestrictedForums']);
            if (!empty($HeavyInfo['PermittedForums'])) {
                $PermittedForums = array_map('trim', explode(',', $HeavyInfo['PermittedForums']));
            } else {
                $PermittedForums = [];
            }
            unset($HeavyInfo['PermittedForums']);
            if (!empty($HeavyInfo['NavItems'])) {
                $NavItems = array_map('trim', explode(',', $HeavyInfo['NavItems']));
            } else {
                $NavItems = [];
            }
            $HeavyInfo['NavItems'] = $NavItems;

            $DB->prepared_query("
                SELECT PermissionID FROM users_levels WHERE UserID = ?
                ", $UserID
            );
            $PermIDs = $DB->collect('PermissionID');
            foreach ($PermIDs AS $PermID) {
                $Perms = Permissions::get_permissions($PermID);
                if (!empty($Perms['PermittedForums'])) {
                    $PermittedForums = array_merge($PermittedForums, array_map('trim', explode(',', $Perms['PermittedForums'])));
                }
            }
            $Perms = Permissions::get_permissions($HeavyInfo['PermissionID']);
            unset($HeavyInfo['PermissionID']);
            if (!empty($Perms['PermittedForums'])) {
                $PermittedForums = array_merge($PermittedForums, array_map('trim', explode(',', $Perms['PermittedForums'])));
            }

            if (!empty($PermittedForums) || !empty($RestrictedForums)) {
                $HeavyInfo['CustomForums'] = [];
                foreach ($RestrictedForums as $ForumID) {
                    $HeavyInfo['CustomForums'][$ForumID] = 0;
                }
                foreach ($PermittedForums as $ForumID) {
                    $HeavyInfo['CustomForums'][$ForumID] = 1;
                }
            } else {
                $HeavyInfo['CustomForums'] = null;
            }
            if (isset($HeavyInfo['CustomForums'][''])) {
                unset($HeavyInfo['CustomForums']['']);
            }

            $HeavyInfo['SiteOptions'] = unserialize_array($HeavyInfo['SiteOptions']);
            $HeavyInfo = array_merge($HeavyInfo, $HeavyInfo['SiteOptions']);
            unset($HeavyInfo['SiteOptions']);
            if (!isset($HeavyInfo['HttpsTracker'])) {
                $HeavyInfo['HttpsTracker'] = true;
            }

            $DB->set_query_id($QueryID);

            $Cache->cache_value("user_info_heavy_$UserID", $HeavyInfo, 0);
        }
        return $HeavyInfo;
    }

    public static function get_user_nav_items($userId) {
        $Info = self::user_heavy_info($userId);

        $UserIds = !empty($Info['NavItems']) ? $Info['NavItems'] : [];
        $NavItems = self::get_nav_items();


        $UserItems = [];
        foreach ($NavItems as $n) {
            if (($n['mandatory'] || in_array($n['id'], $UserIds)) ||
                (!count($UserIds) && $n['initial'])) {
                $UserItems[] = $n;
            }
        }

        return $UserItems;
    }

    public static function get_nav_items() {
        global $Cache, $DB;
        $Items = $Cache->get_value("nav_items");
        if (!$Items) {
            $QueryID = $DB->get_query_id();
            $DB->prepared_query("
                SELECT id, tag, title, target, tests, test_user, mandatory, initial
                FROM nav_items");
            $Items = $DB->to_array("id", MYSQLI_ASSOC);
            $Cache->cache_value("nav_items", $Items, 0);
            $DB->set_query_id($QueryID);
        }
        return $Items;
    }

    /**
     * Updates the site options in the database
     *
     * @param int $UserID the UserID to set the options for
     * @param array $NewOptions the new options to set
     * @return bool false if $NewOptions is empty, true otherwise
     */
    public static function update_site_options($UserID, $NewOptions) {
        if (!is_number($UserID)) {
            error(0);
        }
        if (empty($NewOptions)) {
            return false;
        }

        global $Cache, $DB;
        $QueryID = $DB->get_query_id();

        // Get SiteOptions
        $DB->query("
            SELECT SiteOptions
            FROM users_info
            WHERE UserID = $UserID");
        list($SiteOptions) = $DB->next_record(MYSQLI_NUM, false);
        $SiteOptions = unserialize_array($SiteOptions);
        if (!isset($SiteOptions['HttpsTracker'])) {
            $SiteOptions['HttpsTracker'] = true;
        }

        // Get HeavyInfo
        $HeavyInfo = Users::user_heavy_info($UserID);

        // Insert new/replace old options
        $SiteOptions = array_merge($SiteOptions, $NewOptions);
        $HeavyInfo = array_merge($HeavyInfo, $NewOptions);

        // Update DB
        $DB->prepared_query('
            UPDATE users_info
            SET SiteOptions = ?
            WHERE UserID = ?
            ', $UserID, serialize($SiteOptions)
        );
        $DB->set_query_id($QueryID);

        // Update cache
        $Cache->cache_value("user_info_heavy_$UserID", $HeavyInfo, 0);

        // Update global $LoggedUser if the options are changed for the current
        global $LoggedUser;
        if ($LoggedUser['ID'] == $UserID) {
            $LoggedUser = array_merge($LoggedUser, $NewOptions);
            $LoggedUser['ID'] = $UserID; // We don't want to allow userid switching
        }
        return true;
    }

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

        $Classes = $userMan->classList();
        if ($user->primaryClass() < $Classes[MOD]['Level']) {
            $OverrideParanoia = check_perms('users_override_paranoia', $user->primaryClass());
        } else {
            // Don't override paranoia for mods who don't want to show their donor heart
            $OverrideParanoia = false;
        }
        global $LoggedUser;
        $ShowDonorIcon = $OverrideParanoia || $user->propertyVisible($userMan->findById($LoggedUser['ID']), 'hide_donor_heart');

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
            if ($DonorRank == 0 && $user->isDonor()) {
                $DonorRank = 1;
            }
            if ($ShowDonorIcon && $DonorRank > 0) {
                $EnabledRewards = $user->enabledDonorRewards();
                $DonorRewards = $user->donorRewards();
                $IconText = ($EnabledRewards['HasDonorIconMouseOverText'] && !empty($DonorRewards['IconMouseOverText']))
                    ? display_str($DonorRewards['IconMouseOverText']) : 'Donor';
                $IconLink = ($EnabledRewards['HasDonorIconLink'] && !empty($DonorRewards['CustomIconLink']))
                    ? display_str($DonorRewards['CustomIconLink']) : 'donate.php';
                if ($EnabledRewards['HasCustomDonorIcon'] && !empty($DonorRewards['CustomIcon'])) {
                    $IconImage = ImageTools::process($DonorRewards['CustomIcon'], false, 'donoricon', $UserID);
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
            . ($LoggedUser['ID'] == $UserID ? ' - Expires ' . date('Y-m-d H:i', $user->warningExpiry()) : '')
            . '" class="tooltip" /></a>' : '';
        $Str .= ($IsEnabled && $user->isDisabled())
            ? '<a href="rules.php"><img src="'.STATIC_SERVER.'/common/symbols/disabled.png" alt="Banned" title="Disabled" class="tooltip" /></a>'
            : '';

        if ($Badges) {
            $badgeList = [];
            foreach ($user->secondaryBadges() as $badge => $name) {
                if ($name !== '') {
                    $badgeList[] = '<span class="tooltip secondary_class" title="' . $name . '">' . $badge . '</span>';
                }
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
            if (check_perms('site_proxy_images') && !empty($userTitle)) {
                $userTitle = preg_replace_callback('/src=("?)(http.+?)(["\s>])/',
                    function($Matches) {
                        return 'src=' . $Matches[1] . ImageTools::process($Matches[2]) . $Matches[3];
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
