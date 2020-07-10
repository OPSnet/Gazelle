<?php
class Users {
    /**
     * Get $Classes (list of classes keyed by ID) and $ClassLevels
     *        (list of classes keyed by level)
     * @return array ($Classes, $ClassLevels)
     */
    public static function get_classes() {
        global $Debug;
        // Get permissions
        list($Classes, $ClassLevels) = G::$Cache->get_value('classes');
        if (!$Classes || !$ClassLevels) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query('
                SELECT ID, Name, Level, Secondary, badge
                FROM permissions
                ORDER BY Level');
            $Classes = G::$DB->to_array('ID');
            $ClassLevels = G::$DB->to_array('Level');
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('classes', [$Classes, $ClassLevels], 0);
        }
        $Debug->set_flag('Loaded permissions');

        return [$Classes, $ClassLevels];
    }

    public static function user_stats($UserID, $refresh = false) {
        global $Cache, $DB;
        if ($refresh) {
            $Cache->delete_value('user_stats_'.$UserID);
        }
        $UserStats = $Cache->get_value('user_stats_'.$UserID);
        if (!is_array($UserStats)) {
            $DB->prepared_query('
                SELECT
                    uls.Uploaded AS BytesUploaded,
                    uls.Downloaded AS BytesDownloaded,
                    coalesce(ub.points, 0) as BonusPoints,
                    um.RequiredRatio
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
                WHERE um.ID = ?
                ', $UserID
            );
            $UserStats = $DB->next_record(MYSQLI_ASSOC);
            $Cache->cache_value('user_stats_'.$UserID, $UserStats, 3600);
        }
        return $UserStats;
    }

    /**
     * Get user info, is used for the current user and usernames all over the site.
     *
     * @param $UserID int   The UserID to get info for
     * @return array with the following keys:
     *    int     ID
     *    string  Username
     *    int     PermissionID
     *    array   Paranoia - $Paranoia array sent to paranoia.class
     *    boolean Artist
     *    boolean Donor
     *    string  Warned - When their warning expires in international time format
     *    string  Avatar - URL
     *    boolean Enabled
     *    string  Title
     *    string  CatchupTime - When they last caught up on forums
     *    boolean Visible - If false, they don't show up on peer lists
     *    array   ExtraClasses - Secondary classes.
     *    int     EffectiveClass - the highest level of their main and secondary classes
     */
    public static function user_info($UserID) {
        global $Classes, $SSL;
        $UserInfo = G::$Cache->get_value("user_info_$UserID");
        // the !isset($UserInfo['Paranoia']) can be removed after a transition period
        if (empty($UserInfo) || empty($UserInfo['ID']) || !isset($UserInfo['Paranoia']) || empty($UserInfo['Class'])) {
            $OldQueryID = G::$DB->get_query_id();

            G::$DB->prepared_query("
                SELECT
                    m.ID,
                    m.Username,
                    m.PermissionID,
                    m.Paranoia,
                    i.Artist,
                    (donor.UserID IS NOT NULL) AS Donor,
                    i.Warned,
                    i.Avatar,
                    m.Enabled,
                    m.Title,
                    i.CatchupTime,
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

            if (!G::$DB->has_results()) { // Deleted user, maybe?
                $UserInfo = [
                        'ID' => $UserID,
                        'Username' => '',
                        'PermissionID' => 0,
                        'Paranoia' => [],
                        'Artist' => false,
                        'Donor' => false,
                        'Warned' => null,
                        'Avatar' => '',
                        'Enabled' => 0,
                        'Title' => '',
                        'CatchupTime' => 0,
                        'Visible' => '1',
                        'Levels' => '',
                        'Class' => 0];
            } else {
                $UserInfo = G::$DB->next_record(MYSQLI_ASSOC, ['Paranoia', 'Title']);
                $UserInfo['CatchupTime'] = strtotime($UserInfo['CatchupTime']);
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
            $EffectiveClass = $UserInfo['Class'];
            foreach ($UserInfo['ExtraClasses'] as $Class => $Val) {
                $EffectiveClass = max($EffectiveClass, $Classes[$Class]['Level']);
            }
            $UserInfo['EffectiveClass'] = $EffectiveClass;

            G::$Cache->cache_value("user_info_$UserID", $UserInfo, 2592000);
            G::$DB->set_query_id($OldQueryID);
        }
        if (strtotime($UserInfo['Warned']) < time()) {
            $UserInfo['Warned'] = null;
            G::$Cache->cache_value("user_info_$UserID", $UserInfo, 2592000);
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

        $HeavyInfo = G::$Cache->get_value("user_info_heavy_$UserID");
        if (empty($HeavyInfo)) {

            $QueryID = G::$DB->get_query_id();
            G::$DB->prepared_query('
                SELECT
                    m.Invites,
                    m.torrent_pass,
                    m.IP,
                    m.CustomPermissions,
                    m.can_leech AS CanLeech,
                    m.IRCKey,
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
                    i.LastReadNews,
                    i.LastReadBlog,
                    i.RestrictedForums,
                    i.PermittedForums,
                    i.NavItems,
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
            $HeavyInfo = G::$DB->next_record(MYSQLI_ASSOC, ['CustomPermissions', 'SiteOptions']);
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

            G::$DB->query("
                SELECT PermissionID
                FROM users_levels
                WHERE UserID = $UserID");
            $PermIDs = G::$DB->collect('PermissionID');
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
            $HeavyInfo['SiteOptions'] = array_merge(static::default_site_options(), $HeavyInfo['SiteOptions']);
            $HeavyInfo = array_merge($HeavyInfo, $HeavyInfo['SiteOptions']);

            unset($HeavyInfo['SiteOptions']);

            G::$DB->set_query_id($QueryID);

            G::$Cache->cache_value("user_info_heavy_$UserID", $HeavyInfo, 0);
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
        $Items = G::$Cache->get_value("nav_items");
        if (!$Items) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->prepared_query("
                SELECT id, tag, title, target, tests, test_user, mandatory, initial
                FROM nav_items");
            $Items = G::$DB->to_array("id", MYSQLI_ASSOC);
            G::$Cache->cache_value("nav_items", $Items, 0);
            G::$DB->set_query_id($QueryID);
        }
        return $Items;
    }

    /**
     * Return the ID of a Username
     * @param string Username
     * @return userID if exists, null otherwise
     */
    public static function ID_from_username($name) {
        $digest = base64_encode(md5($name, true));
        $key = "username_id_$digest";
        $ID = G::$Cache->get_value($key);
        if ($ID == -1) {
            return null;
        }
        elseif ($ID === false) {
            G::$DB->prepared_query("SELECT ID FROM users_main WHERE Username=?", $name);
            if (!G::$DB->has_results()) {
                // cache negative hits for a while
                G::$Cache->cache_value($key, -1, 300);
                return null;
            }
            list($ID) = G::$DB->next_record();
            G::$Cache->cache_value($key, $ID, 300);
        }
        return $ID;
    }

    /**
     * Does this ID point to an existing user?
     * @param integer ID
     * @return boolean
     */
    public static function exists($ID) {
        G::$DB->prepared_query("SELECT 1 FROM users_main WHERE ID = ?", $ID);
        return G::$DB->has_results();
    }

    /**
     * Default settings to use for SiteOptions
     * @return array
     */
    public static function default_site_options() {
        return [
            'HttpsTracker' => true
        ];
    }

    /**
     * Updates the site options in the database
     *
     * @param int $UserID the UserID to set the options for
     * @param array $NewOptions the new options to set
     * @return false if $NewOptions is empty, true otherwise
     */
    public static function update_site_options($UserID, $NewOptions) {
        if (!is_number($UserID)) {
            error(0);
        }
        if (empty($NewOptions)) {
            return false;
        }

        $QueryID = G::$DB->get_query_id();

        // Get SiteOptions
        G::$DB->query("
            SELECT SiteOptions
            FROM users_info
            WHERE UserID = $UserID");
        list($SiteOptions) = G::$DB->next_record(MYSQLI_NUM, false);
        $SiteOptions = unserialize_array($SiteOptions);
        $SiteOptions = array_merge(static::default_site_options(), $SiteOptions);

        // Get HeavyInfo
        $HeavyInfo = Users::user_heavy_info($UserID);

        // Insert new/replace old options
        $SiteOptions = array_merge($SiteOptions, $NewOptions);
        $HeavyInfo = array_merge($HeavyInfo, $NewOptions);

        // Update DB
        G::$DB->prepared_query('
            UPDATE users_info
            SET SiteOptions = ?
            WHERE UserID = ?
            ', $UserID, serialize($SiteOptions)
        );
        G::$DB->set_query_id($QueryID);

        // Update cache
        G::$Cache->cache_value("user_info_heavy_$UserID", $HeavyInfo, 0);

        // Update G::$LoggedUser if the options are changed for the current
        if (G::$LoggedUser['ID'] == $UserID) {
            G::$LoggedUser = array_merge(G::$LoggedUser, $NewOptions);
            G::$LoggedUser['ID'] = $UserID; // We don't want to allow userid switching
        }
        return true;
    }

    /**
     * Generates a check list of release types, ordered by the user or default
     * @param array $SiteOptions
     * @param boolean $Default Returns the default list if true
     */
    public static function release_order(&$SiteOptions, $Default = false) {
        global $ReleaseTypes;

        $RT = $ReleaseTypes + [
            1024 => 'Guest Appearance',
            1023 => 'Remixed By',
            1022 => 'Composition',
            1021 => 'Produced By'];

        if ($Default || empty($SiteOptions['SortHide'])) {
            $Sort =& $RT;
            $Defaults = !empty($SiteOptions['HideTypes']);
        } else {
            $Sort =& $SiteOptions['SortHide'];
            $MissingTypes = array_diff_key($RT, $Sort);
            if (!empty($MissingTypes)) {
                foreach (array_keys($MissingTypes) as $Missing) {
                    $Sort[$Missing] = 0;
                }
            }
        }

        foreach ($Sort as $Key => $Val) {
            if (isset($Defaults)) {
                $Checked = $Defaults && isset($SiteOptions['HideTypes'][$Key]) ? ' checked="checked"' : '';
            } else {
                if (!isset($RT[$Key])) {
                    continue;
                }
                $Checked = $Val ? ' checked="checked"' : '';
                $Val = $RT[$Key];
            }

            $ID = $Key. '_' . (int)(!!$Checked);

                            // The HTML is indented this far for proper indentation in the generated HTML
                            // on user.php?action=edit
?>
                            <li class="sortable_item">
                                <label><input type="checkbox"<?=$Checked?> id="<?=$ID?>" /> <?=$Val?></label>
                            </li>
<?php
        }
    }

    /**
     * Returns the default order for the sort list in a JS-friendly string
     * @return string
     */
    public static function release_order_default_js(&$SiteOptions) {
        ob_start();
        self::release_order($SiteOptions, true);
        $HTML = ob_get_contents();
        ob_end_clean();
        return json_encode($HTML);
    }

    /**
     * Verify a password against a password hash
     *
     * @param string $Password password
     * @param string $Hash password hash
     * @return bool  true on correct password
     */
    public static function check_password($Password, $Hash) {
        if (empty($Password) || empty($Hash)) {
            return false;
        }

        return password_verify(hash('sha256', $Password), $Hash) || password_verify($Password, $Hash);
    }

    public static function check_password_old($Password, $Hash) {
        if (empty($Password) || empty($Hash)) {
            return false;
        }

        return password_verify($Password, $Hash);
    }

    /**
     * Create salted crypt hash for a given string with
     * settings specified in CRYPT_HASH_PREFIX
     *
     * @param string  $Str string to hash
     * @return string hashed password
     */
    public static function make_password_hash($Str) {
        return password_hash(hash('sha256', $Str), PASSWORD_DEFAULT);
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
        global $Classes;

        if ($UserID == 0) {
            return 'System';
        }

        $UserInfo = self::user_info($UserID);
        if ($UserInfo['Username'] == '') {
            return "Unknown [$UserID]";
        }

        $Str = '';

        $Username = $UserInfo['Username'];
        $Paranoia = $UserInfo['Paranoia'];

        if ($UserInfo['Class'] < $Classes[MOD]['Level']) {
            $OverrideParanoia = check_perms('users_override_paranoia', $UserInfo['Class']);
        } else {
            // Don't override paranoia for mods who don't want to show their donor heart
            $OverrideParanoia = false;
        }
        $ShowDonorIcon = (!in_array('hide_donor_heart', $Paranoia) || $OverrideParanoia);

        $donorMan = new \Gazelle\Manager\Donation;
        if ($IsDonorForum) {
            list($Prefix, $Suffix, $HasComma) = $donorMan->titles($UserID);
            $Username = "$Prefix $Username" . ($HasComma ? ', ' : ' ') . "$Suffix ";
        }

        if ($Title) {
            $Str .= "<strong><a href=\"user.php?id=$UserID\">$Username</a></strong>";
        } else {
            $Str .= "<a href=\"user.php?id=$UserID\">$Username</a>";
        }
        if ($Badges) {
            $DonorRank = $donorMan->rank($UserID);
            if ($DonorRank == 0 && $UserInfo['Donor'] == 1) {
                $DonorRank = 1;
            }
            if ($ShowDonorIcon && $DonorRank > 0) {
                $IconLink = 'donate.php';
                $IconImage = 'donor.png';
                $IconText = 'Donor';
                $DonorHeart = $DonorRank;
                $SpecialRank = $donorMan->specialRank($UserID);
                $EnabledRewards = $donorMan->enabledRewards($UserID);
                $DonorRewards = $donorMan->rewards($UserID);
                if ($EnabledRewards['HasDonorIconMouseOverText'] && !empty($DonorRewards['IconMouseOverText'])) {
                    $IconText = display_str($DonorRewards['IconMouseOverText']);
                }
                if ($EnabledRewards['HasDonorIconLink'] && !empty($DonorRewards['CustomIconLink'])) {
                    $IconLink = display_str($DonorRewards['CustomIconLink']);
                }
                if ($EnabledRewards['HasCustomDonorIcon'] && !empty($DonorRewards['CustomIcon'])) {
                    $IconImage = ImageTools::process($DonorRewards['CustomIcon'], false, 'donoricon', $UserID);
                } else {
                    if ($SpecialRank === MAX_SPECIAL_RANK) {
                        $DonorHeart = 6;
                    } elseif ($DonorRank === 5) {
                        $DonorHeart = 4; // Two points between rank 4 and 5
                    } elseif ($DonorRank >= MAX_RANK) {
                        $DonorHeart = 5;
                    }
                    if ($DonorHeart === 1) {
                        $IconImage = STATIC_SERVER . 'common/symbols/donor.png';
                    } else {
                        $IconImage = STATIC_SERVER . "common/symbols/donor_{$DonorHeart}.png";
                    }
                }
                $Str .= "<a target=\"_blank\" href=\"$IconLink\"><img class=\"donor_icon tooltip\" src=\"$IconImage\" alt=\"$IconText\" title=\"$IconText\" /></a>";
            }
        }

        $Str .= ($IsWarned && $UserInfo['Warned']) ? '<a href="wiki.php?action=article&amp;name=warnings"'
                    . '><img src="'.STATIC_SERVER.'common/symbols/warned.png" alt="Warned" title="Warned'
                    . (G::$LoggedUser['ID'] == $UserID ? ' - Expires ' . date('Y-m-d H:i', strtotime($UserInfo['Warned'])) : '')
                    . '" class="tooltip" /></a>' : '';
        $Str .= ($IsEnabled && $UserInfo['Enabled'] == 2) ? '<a href="rules.php"><img src="'.STATIC_SERVER.'common/symbols/disabled.png" alt="Banned" title="Disabled" class="tooltip" /></a>' : '';

        if ($Badges) {
            $ClassesDisplay = [];
            foreach (array_keys($UserInfo['ExtraClasses']) as $PermID) {
                if ($Classes[$PermID]['badge'] !== '') {
                    $ClassesDisplay[] = '<span class="tooltip secondary_class" title="'.$Classes[$PermID]['Name'].'">'.$Classes[$PermID]['badge'].'</span>';
                }
            }
            if (!empty($ClassesDisplay)) {
                $Str .= '&nbsp;'.implode('&nbsp;', $ClassesDisplay);
            }
        }

        if ($Class) {
            if ($Title) {
                $Str .= ' <strong>('.Users::make_class_string($UserInfo['PermissionID']).')</strong>';
            } else {
                $Str .= ' ('.Users::make_class_string($UserInfo['PermissionID']).')';
            }
        }

        if ($Title) {
            // Image proxy CTs
            if (check_perms('site_proxy_images') && !empty($UserInfo['Title'])) {
                $UserInfo['Title'] = preg_replace_callback('~src=("?)(http.+?)(["\s>])~',
                    function($Matches) {
                        return 'src=' . $Matches[1] . ImageTools::process($Matches[2]) . $Matches[3];
                    },
                    $UserInfo['Title']);
            }

            if ($UserInfo['Title']) {
                $Str .= ' <span class="user_title">('.$UserInfo['Title'].')</span>';
            }
        }
        return $Str;
    }

    /**
     * Given a class ID, return its name.
     *
     * @param int $ClassID
     * @return string name
     */
    public static function make_class_string($ClassID) {
        global $Classes;
        return $Classes[$ClassID]['Name'];
    }

    /**
     * Returns an array with User Bookmark data: group IDs, collage data, torrent data
     * @param string|int $UserID
     * @return array Group IDs, Bookmark Data, Torrent List
     */
    public static function get_bookmarks($UserID) {
        $UserID = (int)$UserID;

        if (($Data = G::$Cache->get_value("bookmarks_group_ids_$UserID"))) {
            list($GroupIDs, $BookmarkData) = $Data;
        } else {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT GroupID, Sort, `Time`
                FROM bookmarks_torrents
                WHERE UserID = $UserID
                ORDER BY Sort, `Time` ASC");
            $GroupIDs = G::$DB->collect('GroupID');
            $BookmarkData = G::$DB->to_array('GroupID', MYSQLI_ASSOC);
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value("bookmarks_group_ids_$UserID",
                [$GroupIDs, $BookmarkData], 3600);
        }

        $TorrentList = Torrents::get_groups($GroupIDs);

        return [$GroupIDs, $BookmarkData, $TorrentList];
    }

    /**
     * Generate HTML for a user's avatar or just return the avatar URL
     * @param unknown $Avatar
     * @param unknown $UserID
     * @param unknown $Username
     * @param unknown $Setting
     * @param number $Size
     * @param string $ReturnHTML
     * @return string
     */
    public static function show_avatar($Avatar, $UserID, $Username, $Setting, $Size = 150, $ReturnHTML = True) {
        $Avatar = ImageTools::process($Avatar, false, 'avatar', $UserID);
        $AvatarMouseOverText = '';
        $FirstAvatar = '';
        $SecondAvatar = '';

        $donorMan = new \Gazelle\Manager\Donation;
        $EnabledRewards = $donorMan->enabledRewards($UserID);
        if ($EnabledRewards['HasAvatarMouseOverText']) {
            $Rewards = $donorMan->rewards($UserID);
            $AvatarMouseOverText = $Rewards['AvatarMouseOverText'];
        }
        if (!empty($AvatarMouseOverText)) {
            $AvatarMouseOverText =  "title=\"$AvatarMouseOverText\" alt=\"$AvatarMouseOverText\"";
        } else {
            $AvatarMouseOverText = "alt=\"$Username's avatar\"";
        }
        if ($EnabledRewards['HasSecondAvatar'] && !empty($Rewards['SecondAvatar'])) {
            $SecondAvatar = ImageTools::process($Rewards['SecondAvatar'], false, 'avatar2', $UserID);
        }

        $Attrs = "width=\"$Size\" $AvatarMouseOverText";
        // purpose of the switch is to set $FirstAvatar (URL)
        // case 1 is avatars disabled
        switch ($Setting) {
            case 0:
                if (!empty($Avatar)) {
                    $FirstAvatar = $Avatar;
                } else {
                    $FirstAvatar = STATIC_SERVER.'common/avatars/default.png';
                }
                break;
            case 2:
                $ShowAvatar = true;
                // Fallthrough
            case 3:
                if ($ShowAvatar && !empty($Avatar)) {
                    $FirstAvatar = $Avatar;
                    break;
                }
                switch (G::$LoggedUser['Identicons']) {
                    case 0:
                        $Type = 'identicon';
                        break;
                    case 1:
                        $Type = 'monsterid';
                        break;
                    case 2:
                        $Type = 'wavatar';
                        break;
                    case 3:
                        $Type = 'retro';
                        break;
                    case 4:
                        $Type = '1';
                        $Robot = true;
                        break;
                    case 5:
                        $Type = '2';
                        $Robot = true;
                        break;
                    case 6:
                        $Type = '3';
                        $Robot = true;
                        break;
                    default:
                        $Type = 'identicon';
                }
                $Rating = 'pg';
                if (!$Robot) {
                    $FirstAvatar = 'https://secure.gravatar.com/avatar/'.md5(strtolower(trim($Username)))."?s=$Size&amp;d=$Type&amp;r=$Rating";
                } else {
                    $FirstAvatar = 'https://robohash.org/'.md5($Username)."?set=set$Type&amp;size={$Size}x$Size";
                }
                break;
            default:
                $FirstAvatar = STATIC_SERVER.'common/avatars/default.png';
        }
        // in this case, $Attrs is actually just a URL
        if (!$ReturnHTML) {
            return $FirstAvatar;
        }
        $ToReturn = '<div class="avatar_container">';
        foreach ([$FirstAvatar, $SecondAvatar] as $AvatarNum => $CurAvatar) {
            if ($CurAvatar) {
                $ToReturn .= "<div><img $Attrs class=\"avatar_$AvatarNum\" src=\"$CurAvatar\" /></div>";
            }
        }
        $ToReturn .= '</div>';
        return $ToReturn;
    }

    public static function has_avatars_enabled() {
        global $HeavyInfo;
        return isset($HeavyInfo['DisableAvatars']) && $HeavyInfo['DisableAvatars'] != 1;
    }

    /**
     * Checks whether user has autocomplete enabled
     *
     * 0 - Enabled everywhere (default), 1 - Disabled, 2 - Searches only
     *
     * @param string $Type the type of the input.
     * @param boolean $Output echo out HTML
     * @return boolean
     */
    public static function has_autocomplete_enabled($Type, $Output = true) {
        $Enabled = false;
        if (empty(G::$LoggedUser['AutoComplete'])) {
            $Enabled = true;
        } elseif (G::$LoggedUser['AutoComplete'] !== 1) {
            switch ($Type) {
                case 'search':
                    if (G::$LoggedUser['AutoComplete'] == 2) {
                        $Enabled = true;
                    }
                    break;
                case 'other':
                    if (G::$LoggedUser['AutoComplete'] != 2) {
                        $Enabled = true;
                    }
                    break;
            }
        }
        if ($Enabled && $Output) {
            echo ' data-gazelle-autocomplete="true"';
        }
        if (!$Output) {
            // don't return a boolean if you're echoing HTML
            return $Enabled;
        }
    }

    /**
     * Initiate a password reset
     *
     * @param int $UserID The user ID
     * @param string $Username The username
     * @param string $Email The email address
     */
    public static function resetPassword($UserID, $Username, $Email)
    {
        $ResetKey = randomString();
        G::$DB->prepared_query("
            UPDATE users_info
            SET
                ResetKey = ?,
                ResetExpires = ?
            WHERE UserID = ?", $ResetKey, time_plus(60 * 60), $UserID);

        $template = G::$Twig->render('emails/password_reset.twig', [
            'Username' => $Username,
            'ResetKey' => $ResetKey,
            'IP' => $_SERVER['REMOTE_ADDR'],
            'SITE_NAME' => SITE_NAME,
            'SITE_URL' => SITE_URL
        ]);

        Misc::send_email($Email, 'Password reset information for ' . SITE_NAME, $template, 'noreply');
    }

    /**
     * Removes the custom title of a user
     *
     * @param integer $ID The id of the user in users_main
     */
    public static function removeCustomTitle($ID) {
        G::$DB->prepared_query("UPDATE users_main SET Title='' WHERE ID = ? ", $ID);
        G::$Cache->deleteMulti(["user_info_{$ID}", "user_stats_{$ID}"]);
    }

    /**
     * Purchases the custom title for a user
     *
     * @param integer $ID The id of the user in users_main
     * @param string $Title The text of the title (may contain BBcode)
     * @return boolean false if insufficient funds, otherwise true
     */
    public static function setCustomTitle($ID, $Title) {
        G::$DB->prepared_query("UPDATE users_main SET Title = ? WHERE ID = ?",
            $Title, $ID);
        if (G::$DB->affected_rows() == 1) {
            G::$Cache->deleteMulti(["user_info_{$ID}", "user_stats_{$ID}"]);
            return true;
        }
        return false;
    }

    /**
     * Checks whether a user is allowed to purchase an invite. User classes up to Elite are capped,
     * users above this class will always return true.
     *
     * @param integer $ID The id of the user in users_main
     * @param integer $MinClass Minimum class level necessary to purchase invites
     * @return boolean false if insufficient funds, otherwise true
     */
    public static function canPurchaseInvite($ID, $MinClass) {
        $heavy = self::user_heavy_info($ID);
        if ($heavy['DisableInvites']) {
            return false;
        }
        $info = self::user_info($ID);
        return $info['EffectiveClass'] >= $MinClass;
    }

    /**
     * Get the count of enabled users.
     *
     * @return integer Number of enabled users (this is cached).
     */
    public static function get_enabled_users_count() {
        $count = G::$Cache->get_value('stats_user_count');
        if (!$count) {
            G::$DB->query("SELECT count(*) FROM users_main WHERE Enabled = '1'");
            list($count) = G::$DB->next_record();
            G::$Cache->cache_value('stats_user_count', $count, 0);
        }
        return $count;
    }

    /**
     * Flush the count of enabled users. Call a user is enabled or disabled.
     */
    public static function flush_enabled_users_count() {
        G::$Cache->delete_value('stats_user_count');
    }

    /**
     * toggle Accept FL token setting
     * If user accepts FL tokens and the refusal attribute is found, delete it.
     * If user refuses FL tokens and the attribute is not found, insert it.
     */
    public static function toggleAcceptFL($id, $acceptFL) {
        G::$DB->prepared_query('
            SELECT ua.ID
            FROM user_has_attr uha
            INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID)
            WHERE uha.UserID = ?
                AND ua.Name = ?
            ', $id, 'no-fl-gifts'
        );
        $found = G::$DB->has_results();
        if ($acceptFL && $found) {
            list($attr_id) = G::$DB->next_record();
            G::$DB->prepared_query('
                DELETE FROM user_has_attr WHERE UserID = ? AND UserAttrID = ?
                ', $id, $attr_id
            );
        }
        elseif (!$acceptFL && !$found) {
            G::$DB->prepared_query('
                INSERT INTO user_has_attr (UserID, UserAttrID)
                    SELECT ?, ID FROM user_attr WHERE Name = ?
                ', $id, 'no-fl-gifts'
            );
        }
    }

}
