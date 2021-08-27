<?php

class Torrents {
    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists

    /**
     * Function to get data and torrents for an array of GroupIDs. Order of keys doesn't matter
     *
     * @param array $GroupIDs
     * @param boolean $Return if false, nothing is returned. For priming cache.
     * @param boolean $GetArtists if true, each group will contain the result of
     *    Artists::get_artists($GroupID), in result[$GroupID]['ExtendedArtists']
     * @param boolean $Torrents if true, each group contains a list of torrents, in result[$GroupID]['Torrents']
     *
     * @return array each row of the following format:
     * GroupID => (
     *    ID
     *    Name
     *    Year
     *    RecordLabel
     *    CatalogueNumber
     *    TagList
     *    ReleaseType
     *    VanityHouse
     *    WikiImage
     *    CategoryID
     *    Torrents => {
     *        ID => {
     *            GroupID, Media, Format, Encoding, RemasterYear, Remastered,
     *            RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber, Scene,
     *            HasLog, HasCue, LogScore, FileCount, FreeTorrent, Size, Leechers,
     *            Seeders, Snatched, Time, HasFile, PersonalFL, IsSnatched
     *        }
     *    }
     *    Artists => {
     *        {
     *            id, name, aliasid // Only main artists
     *        }
     *    }
     *    ExtendedArtists => {
     *        [1-6] => { // See documentation on Artists::get_artists
     *            id, name, aliasid
     *        }
     *    }
     *    Flags => {
     *        IsSnatched
     *    }
     */
    public static function get_groups($GroupIDs, $Return = true, $GetArtists = true, $Torrents = true) {
        if (count($GroupIDs) === 0) {
            return [];
        }
        $Found = $NotFound = array_fill_keys($GroupIDs, false);
        $Key = $Torrents ? 'torrent_group_' : 'torrent_group_light_';
        global $Cache, $DB;

        foreach ($GroupIDs as $i => $GroupID) {
            if (!is_number($GroupID)) {
                unset($GroupIDs[$i], $Found[$GroupID], $NotFound[$GroupID]);
                continue;
            }
            $Data = $Cache->get_value($Key . $GroupID, true);
            if (!empty($Data) && is_array($Data) && $Data['ver'] == Gazelle\Cache::GROUP_VERSION) {
                unset($NotFound[$GroupID]);
                $Found[$GroupID] = $Data['d'];
            }
        }
        // Make sure there's something in $GroupIDs, otherwise the SQL will break
        if (count($GroupIDs) === 0) {
            return [];
        }

        /*
        Changing any of these attributes returned will cause very large, very dramatic site-wide chaos.
        Do not change what is returned or the order thereof without updating:
            torrents, artists, collages, bookmarks, better, the front page,
        and anywhere else the get_groups function is used.
        Update self::array_group(), too
        */

        if (count($NotFound) > 0) {
            $placeholders = placeholders($NotFound);
            $ids = array_keys($NotFound);
            $NotFound = [];
            $QueryID = $DB->get_query_id();
            $DB->prepared_query("
                SELECT
                    tg.ID, tg.Name, tg.Year, tg.RecordLabel, tg.CatalogueNumber, tg.ReleaseType,
                    tg.VanityHouse, tg.WikiImage, tg.CategoryID,
                    group_concat(t.Name SEPARATOR ' ') AS TagList
                FROM torrents_group tg
                LEFT JOIN torrents_tags tt ON (tt.GroupID = tg.ID)
                LEFT JOIN tags t ON (t.ID = tt.TagID)
                WHERE tg.ID IN ($placeholders)
                GROUP BY tg.ID
                ", ...$ids
            );

            while ($Group = $DB->next_record(MYSQLI_ASSOC, true)) {
                $NotFound[$Group['ID']] = $Group;
                $NotFound[$Group['ID']]['Torrents'] = [];
                $NotFound[$Group['ID']]['Artists'] = [];
            }
            $DB->set_query_id($QueryID);

            if ($Torrents) {
                $QueryID = $DB->get_query_id();
                $DB->prepared_query("
                    SELECT
                        t.ID,
                        t.GroupID,
                        t.Media,
                        t.Format,
                        t.Encoding,
                        t.RemasterYear,
                        t.Remastered,
                        t.RemasterTitle,
                        t.RemasterRecordLabel,
                        t.RemasterCatalogueNumber,
                        t.Scene,
                        t.HasLog,
                        t.HasCue,
                        t.LogScore,
                        t.FileCount,
                        t.FreeTorrent,
                        t.Size,
                        tls.Leechers,
                        tls.Seeders,
                        tls.Snatched,
                        t.Time,
                        t.ID AS HasFile, /* wtf? always true */
                        t.HasLogDB,
                        t.LogChecksum,
                        tbt.TorrentID AS BadTags,
                        tbf.TorrentID AS BadFolders,
                        tfi.TorrentID AS BadFiles,
                        ml.TorrentID AS MissingLineage,
                        ca.TorrentID AS CassetteApproved,
                        lma.TorrentID AS LossymasterApproved,
                        lwa.TorrentID AS LossywebApproved
                    FROM torrents t
                    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                        LEFT JOIN torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
                        LEFT JOIN torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
                        LEFT JOIN torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
                        LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
                        LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
                        LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
                        LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
                    WHERE t.GroupID IN ($placeholders)
                    ORDER BY t.GroupID, t.Remastered, (t.RemasterYear != 0) DESC, t.RemasterYear, t.RemasterTitle,
                            t.RemasterRecordLabel, t.RemasterCatalogueNumber, t.Media, t.Format, t.Encoding, t.ID
                    ", ...$ids
                )
;
                while ($Torrent = $DB->next_record(MYSQLI_ASSOC, true)) {
                    $NotFound[$Torrent['GroupID']]['Torrents'][$Torrent['ID']] = $Torrent;
                }
                $DB->set_query_id($QueryID);
            }

            foreach ($NotFound as $GroupID => $GroupInfo) {
                $Cache->cache_value($Key . $GroupID, ['ver' => Gazelle\Cache::GROUP_VERSION, 'd' => $GroupInfo], 0);
            }

            $Found = $NotFound + $Found;
        }

        // Filter out orphans (elements that are == false)
        $Found = array_filter($Found);

        if ($GetArtists) {
            $Artists = Artists::get_artists($GroupIDs);
        } else {
            $Artists = [];
        }

        if ($Return) { // If we're interested in the data, and not just caching it
            foreach ($Artists as $GroupID => $Data) {
                if (!isset($Found[$GroupID])) {
                    continue;
                }
                if (array_key_exists(1, $Data) || array_key_exists(4, $Data) || array_key_exists(6, $Data)) {
                    $Found[$GroupID]['Artists'] = isset($Data[1]) ? $Data[1] : null; // Only use main artists (legacy)
                    // TODO: find a better solution than this crap / rewrite the artist system
                    for ($i = 1; $i <= 7; $i++) {
                        $Found[$GroupID]['ExtendedArtists'][$i] = isset($Data[$i]) ? $Data[$i] : null;
                    }
                }
                else {
                    $Found[$GroupID]['ExtendedArtists'] = false;
                }
            }
            // Fetch all user specific torrent properties
            if ($Torrents) {
                foreach ($Found as &$Group) {
                    $Group['Flags'] = ['IsSnatched' => false];
                    if (!empty($Group['Torrents'])) {
                        foreach ($Group['Torrents'] as &$Torrent) {
                            self::torrent_properties($Torrent, $Group['Flags']);
                        }
                    }
                }
            }
            return $Found;
        }
    }

    /**
     * Returns a reconfigured array from a Torrent Group
     *
     * DEPRECATED.
     * . added to avoid false positive grep matches
     *
     * Use this with extract.() instead of the volatile list($GroupID, ...)
     * Then use the variables $GroupID, $GroupName, etc
     *
     * @example  extract.(Torrents::array_group($SomeGroup));
     * @param array $Group torrent group
     * @return array Re-key'd array
     */
    public static function array_group(array &$Group) {
        return [
            'GroupID' => $Group['ID'],
            'GroupName' => $Group['Name'],
            'GroupYear' => $Group['Year'],
            'GroupCategoryID' => $Group['CategoryID'],
            'GroupRecordLabel' => $Group['RecordLabel'],
            'GroupCatalogueNumber' => $Group['CatalogueNumber'],
            'GroupVanityHouse' => $Group['VanityHouse'],
            'GroupFlags' => isset($Group['Flags']) ? $Group['Flags'] : ['IsSnatched' => false],
            'TagList' => $Group['TagList'],
            'ReleaseType' => $Group['ReleaseType'],
            'WikiImage' => $Group['WikiImage'],
            'Torrents' => isset($Group['Torrents']) ? $Group['Torrents'] : [],
            'Artists' => $Group['Artists'],
            'ExtendedArtists' => $Group['ExtendedArtists']
        ];
    }

    /**
     * Supplements a torrent array with information that only concerns certain users and therefore cannot be cached
     *
     * @param array $Torrent torrent array preferably in the form used by Torrents::get_groups() or get_group_info()
     * @param int $TorrentID
     */
    public static function torrent_properties(&$Torrent, &$Flags) {
        $Torrent['PersonalFL'] = empty($Torrent['FreeTorrent']) && self::has_token($Torrent['ID']);
        if ($Torrent['IsSnatched'] = self::has_snatched($Torrent['ID'])) {
            $Flags['IsSnatched'] = true;
        }
    }

    /**
     * Delete a group, called after all of its torrents have been deleted.
     * IMPORTANT: Never call this unless you're certain the group is no longer used by any torrents
     *
     * @param int $GroupID
     */
    public static function delete_group($GroupID) {
        global $Cache, $DB;
        $QueryID = $DB->get_query_id();

        (new Gazelle\Log)->general("Group $GroupID automatically deleted (No torrents have this group).");

        $DB->prepared_query("
            SELECT CategoryID
            FROM torrents_group
            WHERE ID = ?", $GroupID);
        list($Category) = $DB->next_record();
        if ($Category == 1) {
            $Cache->decrement('stats_album_count');
        }
        $Cache->decrement('stats_group_count');

        // Collages
        $DB->prepared_query("
            SELECT CollageID
            FROM collages_torrents
            WHERE GroupID = ?", $GroupID);
        if ($DB->has_results()) {
            $CollageIDs = $DB->collect('CollageID');
            $placeholders = placeholders($CollageIDs);
            $DB->prepared_query("
                UPDATE collages
                SET NumTorrents = NumTorrents - 1
                WHERE ID IN ($placeholders)",
                ...$CollageIDs);
            $DB->prepared_query("
                DELETE FROM collages_torrents
                WHERE GroupID = ?", $GroupID);

            foreach ($CollageIDs as $CollageID) {
                $Cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $CollageID));
            }
            $Cache->delete_value("torrent_collages_$GroupID");
        }

        // Artists
        // Collect the artist IDs and then wipe the torrents_artist entry
        $DB->prepared_query("
            SELECT ArtistID
            FROM torrents_artists
            WHERE GroupID = ?", $GroupID);
        $Artists = $DB->collect('ArtistID');

        $DB->prepared_query("
            DELETE FROM torrents_artists
            WHERE GroupID = ?", $GroupID);

        foreach ($Artists as $ArtistID) {
            if (empty($ArtistID)) {
                continue;
            }
            // Get a count of how many groups or requests use the artist ID
            $DB->prepared_query("
                SELECT COUNT(ag.ArtistID)
                FROM artists_group AS ag
                    LEFT JOIN requests_artists AS ra ON ag.ArtistID = ra.ArtistID
                WHERE ra.ArtistID IS NOT NULL
                    AND ag.ArtistID = ?", $ArtistID);
            list($ReqCount) = $DB->next_record();
            $DB->prepared_query('
                SELECT COUNT(ag.ArtistID)
                FROM artists_group AS ag
                    LEFT JOIN torrents_artists AS ta ON ag.ArtistID = ta.ArtistID
                WHERE ta.ArtistID IS NOT NULL
                    AND ag.ArtistID = ?', $ArtistID);
            list($GroupCount) = $DB->next_record();
            if (($ReqCount + $GroupCount) == 0) {
                //The only group to use this artist
                Artists::delete_artist($ArtistID);
            } else {
                //Not the only group, still need to clear cache
                $Cache->delete_value("artist_groups_$ArtistID");
            }
        }

        // Requests
        $DB->prepared_query("
            SELECT ID
            FROM requests
            WHERE GroupID = ?", $GroupID);
        $Requests = $DB->collect('ID');
        $DB->prepared_query("
            UPDATE requests
            SET GroupID = NULL
            WHERE GroupID = ?", $GroupID);
        foreach ($Requests as $RequestID) {
            $Cache->delete_value("request_$RequestID");
        }

        (new \Gazelle\Manager\Comment)->remove('torrents', $GroupID);

        $DB->prepared_query("
            DELETE FROM torrent_group_has_attr
            WHERE TorrentGroupID = ?", $GroupID);
        $DB->prepared_query("
            DELETE FROM torrents_group
            WHERE ID = ?", $GroupID);
        $DB->prepared_query("
            DELETE FROM torrents_tags
            WHERE GroupID = ?", $GroupID);
        $DB->prepared_query("
            DELETE FROM torrents_tags_votes
            WHERE GroupID = ?", $GroupID);
        $DB->prepared_query("
            DELETE FROM bookmarks_torrents
            WHERE GroupID = ?", $GroupID);
        $DB->prepared_query("
            DELETE FROM wiki_torrents
            WHERE PageID = ?", $GroupID);

        $Cache->delete_value("torrents_details_$GroupID");
        $Cache->delete_value("torrent_group_$GroupID");
        $Cache->delete_value("tg_$GroupID");
        $Cache->delete_value("tlist_$GroupID");
        $Cache->delete_value("groups_artists_$GroupID");
        $DB->set_query_id($QueryID);
    }

    /**
     * Format the information about a torrent.
     * @param array $Data an array a subset of the following keys:
     *    Format, Encoding, HasLog, LogScore, HasCue, Media, Scene, RemasterYear
     *    RemasterTitle, FreeTorrent, PersonalFL
     * @param boolean $ShowMedia if false, Media key will be omitted
     * @param boolean $ShowEdition if false, RemasterYear/RemasterTitle will be omitted
     * @return string
     */
    public static function torrent_info($Data, $ShowMedia = false, $ShowEdition = false, $ShowFlags = true, $GroupName = '') {
        $Info = [];
        if (!empty($Data['Format'])) {
            $Info[] = $Data['Format'];
        }
        if (!empty($Data['Encoding'])) {
            $Info[] = $Data['Encoding'];
        }
        if (!empty($Data['Media']) && $Data['Media'] === 'CD') {
            if (!empty($Data['HasLog'])) {
                $Str = 'Log';
                if (!empty($Data['HasLogDB'])) {
                    $Str .= ' ('.$Data['LogScore'].'%)';
                }
                $Info[] = $Str;
            }
            if (!empty($Data['HasCue'])) {
                $Info[] = 'Cue';
            }
        }
        if ($ShowMedia && !empty($Data['Media'])) {
            $Info[] = $Data['Media'];
        }
        if (!empty($Data['Scene'])) {
            $Info[] = 'Scene';
        }
        if (!count($Info) && $GroupName != '') {
            $Info[] = $GroupName;
        }
        if ($ShowEdition) {
            $EditionInfo = [];
            if (!empty($Data['RemasterYear'])) {
                $EditionInfo[] = $Data['RemasterYear'];
            }
            if (!empty($Data['RemasterTitle'])) {
                $EditionInfo[] = $Data['RemasterTitle'];
            }
            if (count($EditionInfo)) {
                $Info[] = implode(' ', $EditionInfo);
            }
        }
        if (!empty($Data['IsSnatched'])) {
            $Info[] = Format::torrent_label('Snatched!');
        }
        if (isset($Data['FreeTorrent'])) {
            if ($Data['FreeTorrent'] == '1') {
                $Info[] = Format::torrent_label('Freeleech!');
            }
            if ($Data['FreeTorrent'] == '2') {
                $Info[] = Format::torrent_label('Neutral Leech!');
            }
        }
        if (!empty($Data['PersonalFL'])) {
            $Info[] = Format::torrent_label('Personal Freeleech!');
        }
        if (!empty($Data['Reported'])) {
            $Info[] = Format::torrent_label('Reported');
        }

        if ($ShowFlags) {
            if ($Data['HasLog'] && $Data['HasLogDB'] && !in_array($Data['LogChecksum'], ['1', true])) {
                $Info[] = Format::torrent_label('Bad/Missing Checksum');
            }
            if (in_array($Data['BadTags'], ['1', true])) {
                $Info[] = Format::torrent_label('Bad Tags');
            }
            if (in_array($Data['BadFolders'], ['1', true])) {
                $Info[] = Format::torrent_label('Bad Folders');
            }
            if (in_array($Data['MissingLineage'], ['1', true])) {
                $Info[] = Format::torrent_label('Missing Lineage');
            }
            if (in_array($Data['CassetteApproved'], ['1', true])) {
                $Info[] = Format::torrent_label('Cassette Approved');
            }
            if (in_array($Data['LossymasterApproved'], ['1', true])) {
                $Info[] = Format::torrent_label('Lossy Master Approved');
            }
            if (in_array($Data['LossywebApproved'], ['1', true])) {
                $Info[] = Format::torrent_label('Lossy WEB Approved');
            }
            if (in_array($Data['BadFiles'], ['1', true])) {
                $Info[] = Format::torrent_label('Bad File Names');
            }
        }

        return implode(' / ', $Info);
    }


    /**
     * Will freeleech / neutral leech / normalise a set of torrents
     *
     * @param array $TorrentIDs An array of torrent IDs to iterate over
     * @param string $FreeNeutral 0 = normal, 1 = fl, 2 = nl
     * @param string $FreeLeechType 0 = Unknown, 1 = Staff picks, 2 = Perma-FL (Toolbox, etc.), 3 = Vanity House
     * @param bool $AllFL true = all torrents are made FL, false = only lossless torrents are made FL
     */
    public static function freeleech_torrents($TorrentIDs, $FreeNeutral = '1', $FreeLeechType = '0', $AllFL = false) {
        if (!is_array($TorrentIDs)) {
            $TorrentIDs = [$TorrentIDs];
        }

        global $Cache, $DB, $LoggedUser;
        $QueryID = $DB->get_query_id();
        $FL_condition = $AllFL || $FreeLeechType == '0' ? '' : "AND Encoding IN ('24bit Lossless', 'Lossless')";
        $placeholders = placeholders($TorrentIDs);
        $DB->prepared_query("
            UPDATE torrents
            SET FreeTorrent = ?, FreeLeechType = ?
            WHERE ID IN ($placeholders)
                $FL_condition
            ", $FreeNeutral, $FreeLeechType, ...$TorrentIDs
        );

        $DB->prepared_query("
            SELECT ID, GroupID, info_hash
            FROM torrents
            WHERE ID IN ($placeholders)
            ORDER BY GroupID ASC
            ", ...$TorrentIDs
        );
        $Torrents = $DB->to_array(false, MYSQLI_NUM, false);
        $GroupIDs = $DB->collect('GroupID');
        $DB->set_query_id($QueryID);

        $groupLog = new Gazelle\Log;
        foreach ($Torrents as $Torrent) {
            [$TorrentID, $GroupID, $InfoHash] = $Torrent;
            (new Gazelle\Tracker)->update_tracker('update_torrent', ['info_hash' => rawurlencode($InfoHash), 'freetorrent' => $FreeNeutral]);
            $Cache->delete_value("torrent_download_$TorrentID");
            $groupLog->torrent($GroupID, $TorrentID, $LoggedUser['ID'], "marked as freeleech type $FreeLeechType!")
                ->general($LoggedUser['Username']." marked torrent $TorrentID freeleech type $FreeLeechType!");
        }

        $tgroupMan = new \Gazelle\Manager\TGroup;
        foreach ($GroupIDs as $GroupID) {
            $tgroupMan->refresh($GroupID);
        }
    }


    /**
     * Convenience function to allow for passing groups to Torrents::freeleech_torrents()
     *
     * @param array $GroupIDs the groups in question
     * @param string $FreeNeutral see Torrents::freeleech_torrents()
     * @param string $FreeLeechType see Torrents::freeleech_torrents()
     */
    public static function freeleech_groups($GroupIDs, $FreeNeutral = '1', $FreeLeechType = '0') {
        global $DB;
        $QueryID = $DB->get_query_id();

        if (!is_array($GroupIDs)) {
            $GroupIDs = [$GroupIDs];
        }

        $DB->prepared_query("
            SELECT ID
            FROM torrents
            WHERE GroupID IN (" . placeholders($GroupIDs) . ")",
            ...$GroupIDs);
        if ($DB->has_results()) {
            $TorrentIDs = $DB->collect('ID');
            Torrents::freeleech_torrents($TorrentIDs, $FreeNeutral, $FreeLeechType);
        }
        $DB->set_query_id($QueryID);
    }


    /**
     * Check if the logged in user has an active freeleech token
     *
     * @param int $TorrentID
     * @return true if an active token exists
     */
    public static function has_token($TorrentID) {
        global $Cache, $DB, $LoggedUser;
        if (!isset($LoggedUser['ID'])) {
            return false;
        }

        static $TokenTorrents;
        $UserID = $LoggedUser['ID'];
        if (!isset($TokenTorrents)) {
            $TokenTorrents = $Cache->get_value("users_tokens_$UserID");
            if ($TokenTorrents === false) {
                $QueryID = $DB->get_query_id();
                $DB->prepared_query("
                    SELECT TorrentID
                    FROM users_freeleeches
                    WHERE UserID = ?
                        AND Expired = 0",
                    $UserID);
                $TokenTorrents = array_fill_keys($DB->collect('TorrentID', false), true);
                $DB->set_query_id($QueryID);
                $Cache->cache_value("users_tokens_$UserID", $TokenTorrents);
            }
        }
        return isset($TokenTorrents[$TorrentID]);
    }


    /**
     * Check if the logged in user can use a freeleech token on this torrent
     *
     * @param int $Torrent
     * @return boolen True if user is allowed to use a token
     */
    public static function can_use_token($Torrent) {
        global $LoggedUser;
        if (!isset($LoggedUser['ID'])) {
            return false;
        }
        return ($LoggedUser['FLTokens'] >= ceil($Torrent['Size'] / BYTES_PER_FREELEECH_TOKEN)
            && (STACKABLE_FREELEECH_TOKENS || $Torrent['Size'] < BYTES_PER_FREELEECH_TOKEN)
            && !$Torrent['PersonalFL']
            && empty($Torrent['FreeTorrent'])
            && $LoggedUser['CanLeech'] == '1');
    }

    /**
     * Build snatchlists and check if a torrent has been snatched
     * if a user has the 'ShowSnatched' option enabled
     * @param int $TorrentID
     * @return bool
     */
    public static function has_snatched($TorrentID) {
        global $Cache, $DB, $LoggedUser;
        if (!isset($LoggedUser['ShowSnatched'])) {
            return false;
        }

        $UserID = $LoggedUser['ID'];
        $Buckets = 64;
        $LastBucket = $Buckets - 1;
        $BucketID = $TorrentID & $LastBucket;
        static $SnatchedTorrents = [], $UpdateTime = [];

        if (empty($SnatchedTorrents)) {
            $SnatchedTorrents = array_fill(0, $Buckets, false);
            $UpdateTime = $Cache->get_value("users_snatched_{$UserID}_time");
            if ($UpdateTime === false) {
                $UpdateTime = [
                    'last' => 0,
                    'next' => 0];
            }
        } elseif (isset($SnatchedTorrents[$BucketID][$TorrentID])) {
            return true;
        }

        // Torrent was not found in the previously inspected snatch lists
        $CurSnatchedTorrents =& $SnatchedTorrents[$BucketID];
        if ($CurSnatchedTorrents === false) {
            $CurTime = time();
            // This bucket hasn't been checked before
            $CurSnatchedTorrents = $Cache->get_value("users_snatched_{$UserID}_$BucketID", true);
            if ($CurSnatchedTorrents === false || $CurTime > $UpdateTime['next']) {
                $Updated = [];
                $QueryID = $DB->get_query_id();
                if ($CurSnatchedTorrents === false || $UpdateTime['last'] == 0) {
                    for ($i = 0; $i < $Buckets; $i++) {
                        $SnatchedTorrents[$i] = [];
                    }
                    // Not found in cache. Since we don't have a suitable index, it's faster to update everything
                    $DB->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ?", $UserID);
                    while (list($ID) = $DB->next_record(MYSQLI_NUM, false)) {
                        $SnatchedTorrents[$ID & $LastBucket][(int)$ID] = true;
                    }
                    $Updated = array_fill(0, $Buckets, true);
                } elseif (isset($CurSnatchedTorrents[$TorrentID])) {
                    // Old cache, but torrent is snatched, so no need to update
                    return true;
                } else {
                    // Old cache, check if torrent has been snatched recently
                    $DB->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ?
                            AND tstamp >= ?",
                        $UserID, $UpdateTime['last']);
                    while (list($ID) = $DB->next_record(MYSQLI_NUM, false)) {
                        $CurBucketID = $ID & $LastBucket;
                        if ($SnatchedTorrents[$CurBucketID] === false) {
                            $SnatchedTorrents[$CurBucketID] = $Cache->get_value("users_snatched_{$UserID}_$CurBucketID", true);
                            if ($SnatchedTorrents[$CurBucketID] === false) {
                                $SnatchedTorrents[$CurBucketID] = [];
                            }
                        }
                        $SnatchedTorrents[$CurBucketID][(int)$ID] = true;
                        $Updated[$CurBucketID] = true;
                    }
                }
                $DB->set_query_id($QueryID);
                for ($i = 0; $i < $Buckets; $i++) {
                    if (isset($Updated[$i])) {
                        $Cache->cache_value("users_snatched_{$UserID}_$i", $SnatchedTorrents[$i], 0);
                    }
                }
                $UpdateTime['last'] = $CurTime;
                $UpdateTime['next'] = $CurTime + self::SNATCHED_UPDATE_INTERVAL;
                $Cache->cache_value("users_snatched_{$UserID}_time", $UpdateTime, 0);
            }
        }
        return isset($CurSnatchedTorrents[$TorrentID]);
    }

    // Some constants for self::display_string's $Mode parameter
    const DISPLAYSTRING_HTML = 1; // Whether or not to use HTML for the output (e.g. VH tooltip)
    const DISPLAYSTRING_ARTISTS = 2; // Whether or not to display artists
    const DISPLAYSTRING_YEAR = 4; // Whether or not to display the group's year
    const DISPLAYSTRING_VH = 8; // Whether or not to display the VH flag
    const DISPLAYSTRING_RELEASETYPE = 16; // Whether or not to display the release type
    const DISPLAYSTRING_LINKED = 33; // Whether or not to link artists and the group
    // The constant for linking is 32, but because linking only works with HTML, this constant is defined as 32|1 = 33, i.e. LINKED also includes HTML
    // Keep this in mind when defining presets below!

    // Presets to facilitate the use of $Mode
    const DISPLAYSTRING_DEFAULT = 63; // HTML|ARTISTS|YEAR|VH|RELEASETYPE|LINKED = 63
    const DISPLAYSTRING_SHORT = 6; // Very simple format, only artists and year, no linking (e.g. for forum thread titles)

    /**
     * Return the display string for a given torrent group $GroupID.
     * @param int $GroupID
     * @return string
     */
    public static function display_string($GroupID, $Mode = self::DISPLAYSTRING_DEFAULT) {
        $GroupInfo = self::get_groups([$GroupID], true, true, false)[$GroupID];
        $ExtendedArtists = $GroupInfo['ExtendedArtists'];

        $DisplayName = '';

        if ($Mode & self::DISPLAYSTRING_ARTISTS) {
            if (!empty($ExtendedArtists[1])
                || !empty($ExtendedArtists[4])
                || !empty($ExtendedArtists[5])
                || !empty($ExtendedArtists[6])
            ) {
                unset($ExtendedArtists[2], $ExtendedArtists[3]);
                $DisplayName = Artists::display_artists($ExtendedArtists, ($Mode & self::DISPLAYSTRING_LINKED));
            } else {
                $DisplayName = '';
            }
        }

        if ($Mode & self::DISPLAYSTRING_LINKED) {
            $DisplayName .= "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">{$GroupInfo['Name']}</a>";
        } else {
            $DisplayName .= $GroupInfo['Name'];
        }

        if (($Mode & self::DISPLAYSTRING_YEAR) && $GroupInfo['Year'] > 0) {
            $DisplayName .= " [" . $GroupInfo['Year'] . "]";
        }

        if (($Mode & self::DISPLAYSTRING_VH) && $GroupInfo['VanityHouse']) {
            if ($Mode & self::DISPLAYSTRING_HTML) {
                $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
            } else {
                $DisplayName .= ' [VH]';
            }
        }

        if (($Mode & self::DISPLAYSTRING_RELEASETYPE) && $GroupInfo['ReleaseType'] > 0) {
            $DisplayName .= ' [' . (new \Gazelle\ReleaseType)->findNameById($GroupInfo['ReleaseType']) . ']';
        }

        return $DisplayName;
    }

    public static function edition_string(array $Torrent, array $Group = []) {
        if ($Torrent['Remastered'] && $Torrent['RemasterYear'] != 0) {
            $EditionName = $Torrent['RemasterYear'];
            $AddExtra = ' - ';
            if ($Torrent['RemasterRecordLabel']) {
                $EditionName .= $AddExtra . display_str($Torrent['RemasterRecordLabel']);
                $AddExtra = ' / ';
            }
            if ($Torrent['RemasterCatalogueNumber']) {
                $EditionName .= $AddExtra . display_str($Torrent['RemasterCatalogueNumber']);
                $AddExtra = ' / ';
            }
            if ($Torrent['RemasterTitle']) {
                $EditionName .= $AddExtra . display_str($Torrent['RemasterTitle']);
                $AddExtra = ' / ';
            }
            $EditionName .= $AddExtra . display_str($Torrent['Media']);
        } else {
            $AddExtra = ' / ';
            if (!$Torrent['Remastered']) {
                $EditionName = 'Original Release';
                if (!empty($Group['RecordLabel'])) {
                    $EditionName .= $AddExtra . $Group['RecordLabel'];
                    $AddExtra = ' / ';
                }
                if (!empty($Group['CatalogueNumber'])) {
                    $EditionName .= $AddExtra . $Group['CatalogueNumber'];
                    $AddExtra = ' / ';
                }
            } else {
                $EditionName = 'Unknown Release(s)';
            }
            $EditionName .= $AddExtra . display_str($Torrent['Media']);
        }
        return $EditionName;
    }

    public static function bbcodeUrl($val, $attr) {
        $cacheKey = 'bbcode_torrent_' . $val;
        if ($attr) {
            $cacheKey .= '_' . $attr;
        }
        global $Cache, $DB;
        if (($url = $Cache->get_value($cacheKey)) === false) {
            $qid = $DB->get_query_id();
            $url = self::bbcodeUrlBuild($val, $attr);
            $DB->set_query_id($qid);
            $Cache->cache_value($cacheKey, $url, 86400 + rand(1, 3600));
        }
        return $url;
    }

    protected static function bbcodeUrlBuild($val, $attr) {
        $id = (int)$val;
        $attr = preg_split('/\s*,\s*/m', strtolower($attr), -1, PREG_SPLIT_NO_EMPTY);
        global $DB;
        $torrent = $DB->rowAssoc("
            SELECT GroupID, Format, Encoding, Media, HasCue, HasLog, HasLogDB, LogScore
            FROM torrents
            WHERE ID = ?
            ", $id
        );
        if (!is_null($torrent)) {
            $isDeleted = false;
        } else {
            $torrent = $DB->rowAssoc("
                SELECT GroupID, Format, Encoding, Media, HasCue, HasLog, HasLogDB, LogScore
                FROM deleted_torrents
                WHERE ID = ?
                ", $id
            );
            if (is_null($torrent)) {
                return ($attr ? "[pl=$attr]" : '[pl]') . $id . '[/pl]';
            }
            $isDeleted = true;
        }
        $group = $DB->rowAssoc("
            SELECT tg.ID,
                tg.Name,
                group_concat(DISTINCT tags.Name SEPARATOR '|') as tagNames,
                tg.Year,
                tg.ReleaseType
            FROM torrents_group AS tg
            LEFT JOIN torrents_tags AS tt ON (tt.GroupID = tg.ID)
            LEFT JOIN tags ON (tags.ID = tt.TagID)
            WHERE tg.ID = ?
            GROUP BY tg.ID
            ", $torrent['GroupID']
        );
        $groupId = $group['ID'];
        $tagNames = implode(', ', array_map(fn($x) => '#' . htmlentities($x), explode('|', $group['tagNames'])));
        $year = in_array('noyear', $attr) || in_array('title', $attr)
            ? '' : (' [' . $group['Year'] . ']');
        $releaseType = (in_array('noreleasetype', $attr) || in_array('title', $attr))
            ? '' : (' [' . (new \Gazelle\ReleaseType)->findNameById($group['ReleaseType']) . ']');
        if (in_array('nometa', $attr) || in_array('title', $attr)) {
            $meta = '';
        } else {
            $details = [
                $torrent['Media'],
                $torrent['Format'],
                $torrent['Encoding'],
            ];
            if ($torrent['HasCue']) {
                $details[] = 'Cue';
            }
            if ($torrent['HasLog']) {
                $log = 'Log';
                if ($torrent['HasLogDB']) {
                    $log .= ' ' . $torrent['LogScore'] . '%';
                }
                $details[] = "$log";
            }
            $meta = ' (' . implode('/', $details) . ')';
        }
        $url = '';
        if (!(in_array('noartist', $attr) || in_array('title', $attr))) {
            $g = self::get_groups([$groupId], true, true, false)[$groupId];
            $url = Artists::display_artists($g['ExtendedArtists']);
        }
        return $url . sprintf(
            '<a title="%s" href="/torrents.php?id=%d&torrentid=%d#torrent%d">%s%s%s</a>%s',
            $tagNames, $groupId, $id, $id, $group['Name'], $year, $releaseType,
            $meta . ($isDeleted ? ' <i>deleted</i>' : '')
        );
    }
}
