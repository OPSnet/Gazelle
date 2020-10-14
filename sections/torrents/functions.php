<?php

function get_group_info($GroupID, $RevisionID = 0, $PersonalProperties = true, $ApiCall = false) {
    global $Cache, $DB;
    if (!$RevisionID) {
        $TorrentCache = $Cache->get_value("torrents_details_$GroupID");
    }
    if ($RevisionID || !is_array($TorrentCache)) {
        // Fetch the group details

        $SQL = 'SELECT ';

        if (!$RevisionID) {
            $SQL .= '
                g.WikiBody,
                g.WikiImage, ';
        } else {
            $SQL .= '
                w.Body,
                w.Image, ';
        }

        $SQL .= "
                g.ID,
                g.Name,
                g.Year,
                g.RecordLabel,
                g.CatalogueNumber,
                g.ReleaseType,
                g.CategoryID,
                g.Time,
                g.VanityHouse,
                GROUP_CONCAT(DISTINCT tags.Name SEPARATOR '|') as tagNames,
                GROUP_CONCAT(DISTINCT tags.ID SEPARATOR '|'),
                GROUP_CONCAT(tt.UserID SEPARATOR '|'),
                GROUP_CONCAT(tt.PositiveVotes SEPARATOR '|'),
                GROUP_CONCAT(tt.NegativeVotes SEPARATOR '|')
            FROM torrents_group AS g
                LEFT JOIN torrents_tags AS tt ON (tt.GroupID = g.ID)
                LEFT JOIN tags ON (tags.ID = tt.TagID)";

        $args = [];
        if ($RevisionID) {
            $SQL .= '
                LEFT JOIN wiki_torrents AS w ON (w.PageID = ? AND w.RevisionID = ?)';
            $args[] = $GroupID;
            $args[] = $RevisionID;
        }

        $SQL .= '
            WHERE g.ID = ?
            GROUP BY g.ID';
        $args[] = $GroupID;

        $DB->prepared_query($SQL, ...$args);
        $TorrentDetails = $DB->next_record(MYSQLI_ASSOC);

        // Fetch the individual torrents
        $columns = "
                t.ID,
                t.Media,
                t.Format,
                t.Encoding,
                t.Remastered,
                t.RemasterYear,
                t.RemasterTitle,
                t.RemasterRecordLabel,
                t.RemasterCatalogueNumber,
                t.Scene,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                t.FileCount,
                t.Size,
                tls.Seeders,
                tls.Leechers,
                tls.Snatched,
                t.FreeTorrent,
                t.Time,
                t.Description,
                t.FileList,
                t.FilePath,
                t.UserID,
                tls.last_action,
                HEX(t.info_hash) AS InfoHash,
                tbt.TorrentID AS BadTags,
                tbf.TorrentID AS BadFolders,
                tfi.TorrentID AS BadFiles,
                ml.TorrentID AS MissingLineage,
                ca.TorrentID AS CassetteApproved,
                lma.TorrentID AS LossymasterApproved,
                lwa.TorrentID AS LossywebApproved,
                t.LastReseedRequest,
                t.ID AS HasFile,
                COUNT(tl.LogID) AS LogCount
        ";

        $DB->prepared_query("
            SELECT $columns
                ,0 as is_deleted
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
            LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
            LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
            LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
            WHERE t.GroupID = ?
            GROUP BY t.ID
            UNION DISTINCT
            SELECT $columns
                ,1 as is_deleted
            FROM deleted_torrents AS t
            INNER JOIN deleted_torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
            LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
            WHERE t.GroupID = ?
            GROUP BY t.ID
            ORDER BY Remastered ASC,
                (RemasterYear != 0) DESC,
                RemasterYear ASC,
                RemasterTitle ASC,
                RemasterRecordLabel ASC,
                RemasterCatalogueNumber ASC,
                Media ASC,
                Format,
                Encoding,
                ID", $GroupID, $GroupID);

        $TorrentList = $DB->to_array('ID', MYSQLI_ASSOC);
        if (empty($TorrentDetails) || empty($TorrentList)) {
            if ($ApiCall === false) {
                header('Location: log.php?search='.(empty($_GET['torrentid']) ? "Group+$GroupID" : "Torrent+{$_GET['torrentid']}"));
                die();
            }
            else {
                return null;
            }
        }
        if (in_array(0, $DB->collect('Seeders'))) {
            $CacheTime = 600;
        } else {
            $CacheTime = 3600;
        }
        // Store it all in cache
        if (!$RevisionID) {
            $Cache->cache_value("torrents_details_$GroupID", [$TorrentDetails, $TorrentList], $CacheTime);
        }
    } else { // If we're reading from cache
        $TorrentDetails = $TorrentCache[0];
        $TorrentList = $TorrentCache[1];
    }

    if ($PersonalProperties) {
        // Fetch all user specific torrent and group properties
        $TorrentDetails['Flags'] = ['IsSnatched' => false];
        foreach ($TorrentList as &$Torrent) {
            Torrents::torrent_properties($Torrent, $TorrentDetails['Flags']);
        }
    }

    return [$TorrentDetails, $TorrentList];
}

function get_torrent_info($TorrentID, $RevisionID = 0, $PersonalProperties = true, $ApiCall = false) {
    $torMan = new \Gazelle\Manager\Torrent;
    $GroupInfo = get_group_info($torMan->idToGroupId($TorrentID), $RevisionID, $PersonalProperties, $ApiCall);
    if (!$GroupInfo) {
        return null;
    }
    foreach ($GroupInfo[1] as &$Torrent) {
        //Remove unneeded entries
        if ($Torrent['ID'] != $TorrentID) {
            unset($GroupInfo[1][$Torrent['ID']]);
        }
        return $GroupInfo;
    }
}

function get_group_requests($GroupID) {
    if (empty($GroupID) || !is_number($GroupID)) {
        return [];
    }
    global $DB, $Cache;

    $Requests = $Cache->get_value("requests_group_$GroupID");
    if ($Requests === false) {
        $DB->prepared_query("
            SELECT ID
            FROM requests
            WHERE TimeFilled IS NULL
                AND GroupID = ?
            ", $GroupID
        );
        $Requests = $DB->collect('ID');
        $Cache->cache_value("requests_group_$GroupID", $Requests, 0);
    }
    return Requests::get_requests($Requests);
}

// Count the number of audio files in a torrent file list per audio type
function audio_file_map($fileList) {
    $map = [];
    foreach (explode("\n", strtolower($fileList)) as $file) {
        $info = Torrents::filelist_get_file($file);
        if (!isset($info['ext'])) {
            continue;
        }
        $ext = substr($info['ext'], 1); // skip over period
        if (in_array($ext, ['ac3', 'flac', 'm4a', 'mp3'])) {
            if (!isset($map[$ext])) {
                $map[$ext] = 0;
            }
            ++$map[$ext];
        }
    }
    return $map;
}

function set_source(
    \OrpheusNET\BencodeTorrent\BencodeTorrent $torrent,
    string $siteSource,
    string $grandfatherSource,
    int $grandfatherSourceDate,
    int $grandfatherNoSourceDate
) {
    $torrentSource = $torrent->getSource();
    $creationDate = $torrent->getCreationDate();

    if ($torrentSource === $siteSource) {
        return false;
    }

    if (!is_null($creationDate)) {
        if (is_null($torrentSource) && $creationDate <= $grandfatherNoSourceDate) {
            return false;
        }
        elseif (!is_null($torrentSource) && $torrentSource === $grandfatherSource && $creationDate <= $grandfatherSourceDate) {
            return false;
        }
    }

    return $torrent->setSource($siteSource);
}
