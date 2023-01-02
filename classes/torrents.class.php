<?php

class Torrents {
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
}
