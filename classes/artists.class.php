<?php
class Artists {
    /**
     * Given an array of GroupIDs, return their associated artists.
     *
     * @param array $GroupIDs
     * @return array an array of the following form:
     *    GroupID => {
     *        [ArtistType] => {
     *            id, name, aliasid
     *        }
     *    }
     * ArtistType is an int. It can be:
     * 1 => Main artist
     * 2 => Guest artist
     * 3 => Remixer
     * 4 => Composer
     * 5 => Conductor
     * 6 => DJ
     * 7 => Producer
     * 8 => Arranger
     */
    public static function get_artists($GroupIDs) {
        global $Cache, $DB;
        $Results = [];
        $DBs = [];
        foreach ($GroupIDs as $GroupID) {
            if (!is_number($GroupID)) {
                continue;
            }
            $Artists = $Cache->get_value('groups_artists_'.$GroupID);
            if (is_array($Artists)) {
                $Results[$GroupID] = $Artists;
            } else {
                $DBs[] = $GroupID;
            }
        }
        if (count($DBs) > 0) {
            $IDs = implode(',', $DBs);
            $QueryID = $DB->get_query_id();
            $DB->prepared_query("
                SELECT ta.GroupID,
                    ta.ArtistID,
                    aa.Name,
                    ta.Importance,
                    ta.AliasID
                FROM torrents_artists AS ta
                    JOIN artists_alias AS aa ON ta.AliasID = aa.AliasID
                WHERE ta.GroupID IN ($IDs)
                ORDER BY ta.GroupID,
                    ta.Importance,
                    aa.Name
            ");
            while (list($GroupID, $ArtistID, $ArtistName, $ArtistImportance, $AliasID) = $DB->next_record(MYSQLI_BOTH, false)) {
                $Results[$GroupID][$ArtistImportance][] = ['id' => $ArtistID, 'name' => $ArtistName, 'aliasid' => $AliasID];
                $New[$GroupID][$ArtistImportance][] = ['id' => $ArtistID, 'name' => $ArtistName, 'aliasid' => $AliasID];
            }
            $DB->set_query_id($QueryID);
            foreach ($DBs as $GroupID) {
                if (isset($New[$GroupID])) {
                    $Cache->cache_value('groups_artists_'.$GroupID, $New[$GroupID]);
                }
                else {
                    $Cache->cache_value('groups_artists_'.$GroupID, []);
                }
            }
            $Missing = array_diff($GroupIDs, array_keys($Results));
            if (!empty($Missing)) {
                $Results += array_fill_keys($Missing, []);
            }
        }
        return $Results;
    }


    /**
     * Convenience function for get_artists, when you just need one group.
     *
     * @param int $GroupID
     * @return array - see get_artists
     */
    public static function get_artist($GroupID) {
        $Results = Artists::get_artists([$GroupID]);
        return $Results[$GroupID];
    }

    /**
     * Format an array of artists for display.
     * TODO: Revisit the logic of this, see if we can helper-function the copypasta.
     *
     * @param array $Artists an array of the form output by get_artists
     * @param boolean $MakeLink if true, the artists will be links, if false, they will be text.
     * @param boolean $IncludeHyphen if true, appends " - " to the end.
     * @param boolean $Escape if true, output will be escaped. Think carefully before setting it false.
     * @return string
     */
    public static function display_artists($Artists, $MakeLink = true, $IncludeHyphen = true, $Escape = true) {
        if (!empty($Artists)) {
            $ampersand = ($Escape) ? ' &amp; ' : ' & ';
            $link = '';

            $MainArtists = isset($Artists[1]) ? $Artists[1] : [];
            $Guests      = isset($Artists[2]) ? $Artists[2] : [];
            $Composers   = isset($Artists[4]) ? $Artists[4] : [];
            $Conductors  = isset($Artists[5]) ? $Artists[5] : [];
            $DJs         = isset($Artists[6]) ? $Artists[6] : [];
            $Arrangers   = isset($Artists[8]) ? $Artists[8] : [];

            $MainArtistCount = count($MainArtists);
            $GuestCount      = count($Guests);
            $ComposerCount   = count($Composers);
            $ConductorCount  = count($Conductors);
            $ArrangerCount   = count($Arrangers);
            $DJCount         = count($DJs);

            if ($MainArtistCount + $ConductorCount + $DJCount + $ComposerCount == 0) {
                return '';
            }
            // Various Composers is not needed and is ugly and should die
            switch ($ComposerCount) {
                case 0:
                    break;
                case 1:
                    $link .= Artists::display_artist($Composers[0], $MakeLink, $Escape);
                    break;
                case 2:
                    $link .= Artists::display_artist($Composers[0], $MakeLink, $Escape).$ampersand.Artists::display_artist($Composers[1], $MakeLink, $Escape);
                    break;
            }

            if (($ComposerCount > 0) && ($ComposerCount < 3) && ($MainArtistCount > 0)) {
                $link .= ' performed by ';
            }

            $ComposerStr = $link;

            switch ($MainArtistCount) {
                case 0:
                    break;
                case 1:
                    $link .= Artists::display_artist($MainArtists[0], $MakeLink, $Escape);
                    break;
                case 2:
                    $link .= Artists::display_artist($MainArtists[0], $MakeLink, $Escape).$ampersand.Artists::display_artist($MainArtists[1], $MakeLink, $Escape);
                    break;
                default:
                    $link .= 'Various Artists';
            }

            if (($ConductorCount > 0) && ($MainArtistCount + $ComposerCount > 0) && ($ComposerCount < 3 || $MainArtistCount > 0)) {
                $link .= ' under ';
            }
            switch ($ConductorCount) {
                case 0:
                    break;
                case 1:
                    $link .= Artists::display_artist($Conductors[0], $MakeLink, $Escape);
                    break;
                case 2:
                    $link .= Artists::display_artist($Conductors[0], $MakeLink, $Escape).$ampersand.Artists::display_artist($Conductors[1], $MakeLink, $Escape);
                    break;
                default:
                    $link .= ' Various Conductors';
            }

            if (($ArrangerCount > 0) && ($MainArtistCount + $ArrangerCount > 0) && ($ArrangerCount < 3 || $MainArtistCount > 0)) {
                $link .= ' arranged by ';
            }
            switch ($ArrangerCount) {
                case 0:
                    break;
                case 1:
                    $link .= Artists::display_artist($Arrangers[0], $MakeLink, $Escape);
                    break;
                case 2:
                    $link .= Artists::display_artist($Arrangers[0], $MakeLink, $Escape).$ampersand.Artists::display_artist($Arrangers[1], $MakeLink, $Escape);
                    break;
                default:
                    $link .= ' Various Arrangers';
            }

            if (($ComposerCount > 0) && ($MainArtistCount + $ConductorCount > 3) && ($MainArtistCount > 1) && ($ConductorCount > 1)) {
                $link = $ComposerStr . 'Various Artists';
            } elseif (($ComposerCount > 2) && ($MainArtistCount + $ConductorCount == 0)) {
                $link = 'Various Composers';
            }

            // DJs override everything else
            switch ($DJCount) {
                case 0:
                    break;
                case 1:
                    $link = Artists::display_artist($DJs[0], $MakeLink, $Escape);
                    break;
                case 2:
                    $link = Artists::display_artist($DJs[0], $MakeLink, $Escape).$ampersand.Artists::display_artist($DJs[1], $MakeLink, $Escape);
                    break;
                default:
                    $link = 'Various DJs';
            }
            return $link.($IncludeHyphen?' - ':'');
        } else {
            return '';
        }
    }


    /**
     * Formats a single artist name.
     *
     * @param array $Artist an array of the form ('id'=>ID, 'name'=>Name)
     * @param boolean $MakeLink If true, links to the artist page.
     * @param boolean $Escape If false and $MakeLink is false, returns the unescaped, unadorned artist name.
     * @return string Formatted artist name.
     */
    public static function display_artist($Artist, $MakeLink = true, $Escape = true) {
        if ($MakeLink && !$Escape) {
            error('Invalid parameters to Artists::display_artist()');
            return '';
        } elseif ($MakeLink) {
            return '<a href="artist.php?id='.$Artist['id'].'" dir="ltr">'.display_str($Artist['name']).'</a>';
        } elseif ($Escape) {
            return display_str($Artist['name']);
        } else {
            return $Artist['name'];
        }
    }
}
