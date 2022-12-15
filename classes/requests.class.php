<?php
class Requests {

    /**
     * Function to get data from an array of $RequestIDs. Order of keys doesn't matter (let's keep it that way).
     *
     * @param array $RequestIDs
     * @param boolean $Return if set to false, data won't be returned (ie. if we just want to prime the cache.)
     * @return array The array of requests.
     * Format: array(RequestID => Associative array)
     * To see what's exactly inside each associate array, peek inside the function. It won't bite.
     */
    //
    //In places where the output from this is merged with sphinx filters, it will be in a different order.
    public static function get_requests($RequestIDs, $Return = true): array {
        global $Cache, $DB;
        $Found = [];
        $NotFound = [];
        // Try to fetch the requests from the cache first.
        foreach ($RequestIDs as $i => $RequestID) {
            $RequestID = (int)$RequestID;
            if (!$RequestID) {
                continue;
            }
            $Data = $Cache->get_value("request_$RequestID");
            if ($Data) {
                $Found[$RequestID] = $Data;
            } else {
                $NotFound[$RequestID] = true;
            }
        }

        /*
            Don't change without ensuring you change everything else that uses get_requests()
        */

        if (count($NotFound) > 0) {
            $QueryID = $DB->get_query_id();
            $ids = array_keys($NotFound);
            $DB->prepared_query("
                SELECT ID,
                    UserID,
                    TimeAdded,
                    LastVote,
                    CategoryID,
                    Title,
                    Year,
                    Image,
                    Description,
                    CatalogueNumber,
                    RecordLabel,
                    ReleaseType,
                    BitrateList,
                    FormatList,
                    MediaList,
                    LogCue,
                    Checksum,
                    FillerID,
                    TorrentID,
                    TimeFilled,
                    GroupID,
                    OCLC
                FROM requests
                WHERE ID IN (" . placeholders($ids) . ")
                ", ...$ids
            );
            $Requests = $DB->to_array(false, MYSQLI_ASSOC, true);
            $Tags = self::get_tags($DB->collect('ID', false));
            foreach ($Requests as $Request) {
                unset($NotFound[$Request['ID']]);
                $Request['Tags'] = isset($Tags[$Request['ID']]) ? $Tags[$Request['ID']] : [];
                $Found[$Request['ID']] = $Request;
                $Cache->cache_value('request_'.$Request['ID'], $Request, 0);
            }
            $DB->set_query_id($QueryID);

            // Orphan requests. There should never be any
            if (count($NotFound) > 0) {
                foreach (array_keys($NotFound) as $GroupID) {
                    unset($Found[$GroupID]);
                }
            }
        }

        return $Return ? $Found : [];
    }

    /**
     * Return a single request. Wrapper for get_requests
     *
     * @param int $RequestID
     * @return array|false request array or false if request doesn't exist. See get_requests for a description of the format
     */
    public static function get_request($RequestID) {
        $Request = self::get_requests([$RequestID]);
        if (isset($Request[$RequestID])) {
            return $Request[$RequestID];
        }
        return false;
    }

    public static function get_artists($RequestID) {
        global $Cache, $DB;
        $Artists = $Cache->get_value("request_artists_$RequestID");
        if (is_array($Artists)) {
            $Results = $Artists;
        } else {
            $Results = [];
            $QueryID = $DB->get_query_id();
            $DB->prepared_query('
                SELECT
                    ra.ArtistID,
                    aa.Name,
                    ra.Importance
                FROM requests_artists AS ra
                INNER JOIN artists_alias AS aa ON ra.AliasID = aa.AliasID
                WHERE ra.RequestID = ?
                ORDER BY ra.Importance ASC, aa.Name ASC',
                $RequestID);
            $ArtistRaw = $DB->to_array();
            $DB->set_query_id($QueryID);
            foreach ($ArtistRaw as $ArtistRow) {
                list($ArtistID, $ArtistName, $ArtistImportance) = $ArtistRow;
                $Results[$ArtistImportance][] = ['id' => $ArtistID, 'name' => $ArtistName];
            }
            $Cache->cache_value("request_artists_$RequestID", $Results);
        }
        return $Results;
    }

    /**
     * Return artists of a group as an array of types (main, composer, guest, ...)
     * @param int $RequestID
     * @return array - associative array with the keys:
     *    (composers, dj, artists, with, conductor, remixedBy, producer)
     *    If there are no artists of a given type, the array will be empty,
     *    otherwise each artist is represented as an [id, name] array.
     * TODO: This is copypasta from the Artists class.
     */
    public static function get_artist_by_type($RequestID) {
        $map = [
            1 => 'artists',
            2 => 'with',
            3 => 'remixedBy',
            4 => 'composers',
            5 => 'conductor',
            6 => 'dj',
            7 => 'producer',
            8 => 'arranger',
        ];
        $artist = self::get_artists($RequestID);

        $result = [];
        foreach ($map as $type => $label) {
            $result[$label] = !isset($artist[$type])
                ? []
                : array_map(
                    function ($x) {
                        return ['id' => $x['id'], 'name' => $x['name']];
                    },
                    $artist[$type]
                );
        }
        return $result;
    }

    public static function get_tags(array $RequestIDs): array {
        if (empty($RequestIDs)) {
            return [];
        }
        global $DB;
        $QueryID = $DB->get_query_id();
        $DB->prepared_query("
            SELECT rt.RequestID,
                rt.TagID,
                t.Name
            FROM requests_tags AS rt
            INNER JOIN tags AS t ON (t.ID = rt.TagID)
            WHERE rt.RequestID IN (" . placeholders($RequestIDs) . ")
            ORDER BY rt.TagID ASC
            ", ...$RequestIDs
        );
        $Tags = $DB->to_array(false, MYSQLI_ASSOC, false);
        $DB->set_query_id($QueryID);
        $Results = [];
        foreach ($Tags as $TagsRow) {
            $Results[$TagsRow['RequestID']][$TagsRow['TagID']] = $TagsRow['Name'];
        }
        return $Results;
    }
}
