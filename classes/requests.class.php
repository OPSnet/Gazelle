<?php
class Requests {
    /**
     * Update the sphinx requests delta table for a request.
     *
     * @param $RequestID
     */
    public static function update_sphinx_requests($RequestID) {
        global $Cache, $DB;
        $QueryID = $DB->get_query_id();

        $DB->prepared_query("
            SELECT REPLACE(t.Name, '.', '_')
            FROM tags AS t
            INNER JOIN requests_tags AS rt ON t.ID = rt.TagID
            WHERE rt.RequestID = ?", $RequestID);
        $TagList = $DB->collect(0, false);

        $DB->prepared_query('
            REPLACE INTO sphinx_requests_delta (
                ID, UserID, TimeAdded, LastVote, CategoryID, Title,
                Year, ReleaseType, CatalogueNumber, RecordLabel, BitrateList,
                FormatList, MediaList, LogCue, FillerID, TorrentID,
                TimeFilled, Visible, Votes, Bounty, TagList)
            SELECT
                ID, r.UserID, UNIX_TIMESTAMP(TimeAdded) AS TimeAdded,
                UNIX_TIMESTAMP(LastVote) AS LastVote, CategoryID, Title,
                Year, ReleaseType, CatalogueNumber, RecordLabel, BitrateList,
                FormatList, MediaList, LogCue, FillerID, TorrentID,
                UNIX_TIMESTAMP(TimeFilled) AS TimeFilled, Visible,
                COUNT(rv.UserID) AS Votes, SUM(rv.Bounty) >> 10 AS Bounty,
                ?
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON rv.RequestID = r.ID
            WHERE r.ID = ?
            GROUP BY r.ID',
            implode(' ', $TagList), $RequestID);
        $DB->prepared_query("
            UPDATE sphinx_requests_delta
            SET ArtistList = (
                    SELECT GROUP_CONCAT(aa.Name SEPARATOR ' ')
                    FROM requests_artists AS ra
                        JOIN artists_alias AS aa ON aa.AliasID = ra.AliasID
                    WHERE ra.RequestID = $RequestID
                    GROUP BY NULL
                    )
            WHERE ID = ?", $RequestID);
        $DB->set_query_id($QueryID);

        $Cache->delete_value("request_$RequestID");
    }



    /**
     * Function to get data from an array of $RequestIDs. Order of keys doesn't matter (let's keep it that way).
     *
     * @param array $RequestIDs
     * @param boolean $Return if set to false, data won't be returned (ie. if we just want to prime the cache.)
     * @return The array of requests.
     * Format: array(RequestID => Associative array)
     * To see what's exactly inside each associate array, peek inside the function. It won't bite.
     */
    //
    //In places where the output from this is merged with sphinx filters, it will be in a different order.
    public static function get_requests($RequestIDs, $Return = true) {
        $Found = $NotFound = array_fill_keys($RequestIDs, false);
        // Try to fetch the requests from the cache first.
        foreach ($RequestIDs as $i => $RequestID) {
            if (!is_number($RequestID)) {
                unset($RequestIDs[$i], $Found[$RequestID], $NotFound[$RequestID]);
                continue;
            }
            global $Cache;
            $Data = $Cache->get_value("request_$RequestID");
            if (!empty($Data)) {
                unset($NotFound[$RequestID]);
                $Found[$RequestID] = $Data;
            }
        }
        // Make sure there's something in $RequestIDs, otherwise the SQL will break
        if (count($RequestIDs) === 0) {
            return [];
        }
        $IDs = implode(',', array_keys($NotFound));

        /*
            Don't change without ensuring you change everything else that uses get_requests()
        */

        if (count($NotFound) > 0) {
            global $Cache, $DB;
            $QueryID = $DB->get_query_id();

            $DB->query("
                SELECT
                    ID,
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
                WHERE ID IN ($IDs)
                ORDER BY ID");
            $Requests = $DB->to_array(false, MYSQLI_ASSOC, true);
            $Tags = self::get_tags($DB->collect('ID', false));
            foreach ($Requests as $Request) {
                unset($NotFound[$Request['ID']]);
                $Request['Tags'] = isset($Tags[$Request['ID']]) ? $Tags[$Request['ID']] : [];
                $Found[$Request['ID']] = $Request;
                $Cache->cache_value('request_'.$Request['ID'], $Request, 0);
            }
            $DB->set_query_id($QueryID);

            // Orphan requests. There shouldn't ever be any
            if (count($NotFound) > 0) {
                foreach (array_keys($NotFound) as $GroupID) {
                    unset($Found[$GroupID]);
                }
            }
        }

        if ($Return) { // If we're interested in the data, and not just caching it
            return $Found;
        }
    }

    /**
     * Return a single request. Wrapper for get_requests
     *
     * @param int $RequestID
     * @return request array or false if request doesn't exist. See get_requests for a description of the format
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
     * @param int $GroupID
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

    public static function get_tags($RequestIDs) {
        if (empty($RequestIDs)) {
            return [];
        }
        if (is_array($RequestIDs)) {
            $RequestIDs = implode(',', $RequestIDs);
        }
        global $DB;
        $QueryID = $DB->get_query_id();
        $DB->query("
            SELECT
                rt.RequestID,
                rt.TagID,
                t.Name
            FROM requests_tags AS rt
                JOIN tags AS t ON rt.TagID = t.ID
            WHERE rt.RequestID IN ($RequestIDs)
            ORDER BY rt.TagID ASC");
        $Tags = $DB->to_array(false, MYSQLI_NUM, false);
        $DB->set_query_id($QueryID);
        $Results = [];
        foreach ($Tags as $TagsRow) {
            list($RequestID, $TagID, $TagName) = $TagsRow;
            $Results[$RequestID][$TagID] = $TagName;
        }
        return $Results;
    }

    public static function get_votes_array($RequestID) {
        global $Cache, $DB;
        $RequestVotes = $Cache->get_value("request_votes_$RequestID");
        if (!is_array($RequestVotes)) {
            $QueryID = $DB->get_query_id();
            $DB->prepared_query('
                SELECT
                    rv.UserID,
                    rv.Bounty,
                    u.Username
                FROM requests_votes AS rv
                LEFT JOIN users_main AS u ON (u.ID = rv.UserID)
                WHERE rv.RequestID = ?
                ORDER BY rv.Bounty DESC',
                $RequestID);
            if (!$DB->has_results()) {
                return [
                    'TotalBounty' => 0,
                    'Voters' => []
                ];
            }
            $Votes = $DB->to_array();

            $RequestVotes = [];
            $RequestVotes['TotalBounty'] = array_sum($DB->collect('Bounty'));

            $VotesArray = [];
            foreach ($Votes as $Vote) {
                list($UserID, $Bounty, $Username) = $Vote;
                $VotesArray[] = ['UserID' => $UserID, 'Username' => $Username, 'Bounty' => $Bounty];
            }

            $RequestVotes['Voters'] = $VotesArray;
            $Cache->cache_value("request_votes_$RequestID", $RequestVotes);
            $DB->set_query_id($QueryID);
        }
        return $RequestVotes;
    }
}
