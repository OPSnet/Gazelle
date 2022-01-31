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
     * @return void|array each row of the following format:
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
