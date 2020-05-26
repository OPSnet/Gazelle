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
                header('Location: log.php?search='.(empty($_GET['torrentid']) ? "Group+$GroupID" : "Torrent+$_GET[torrentid]"));
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
        $DB->query("
            SELECT ID
            FROM requests
            WHERE GroupID = $GroupID
                AND TimeFilled = '0000-00-00 00:00:00'");
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
        if (in_array($ext, ['flac', 'mp3', 'ac3'])) {
            if (!isset($map[$ext])) {
                $map[$ext] = 0;
            }
            ++$map[$ext];
        }
    }
    return $map;
}

//Used by both sections/torrents/details.php and sections/reportsv2/report.php
function build_torrents_table($Cache, $DB, $LoggedUser, $GroupID, $GroupName, $GroupCategoryID, $ReleaseType, $TorrentList, $Types) {

    function filelist($Str) {
        return "</td>\n<td>" . Format::get_size($Str[1]) . "</td>\n</tr>";
    }

    $LastRemasterYear = '-';
    $LastRemasterTitle = '';
    $LastRemasterRecordLabel = '';
    $LastRemasterCatalogueNumber = '';

    $EditionID = 0;
    foreach ($TorrentList as $Torrent) {
    list($TorrentID, $Media, $Format, $Encoding, $Remastered, $RemasterYear,
        $RemasterTitle, $RemasterRecordLabel, $RemasterCatalogueNumber, $Scene,
        $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $FileCount, $Size, $Seeders, $Leechers,
        $Snatched, $FreeTorrent, $TorrentTime, $Description, $FileList,
        $FilePath, $UserID, $LastActive, $InfoHash, $BadTags, $BadFolders, $BadFiles,
        $MissingLineage, $CassetteApproved, $LossymasterApproved, $LossywebApproved, $LastReseedRequest,
        $HasFile, $LogCount, $PersonalFL, $IsSnatched) = array_values($Torrent);

    $FirstUnknown = ($Remastered && !$RemasterYear);

    $Reported = false;
    $Reports = Torrents::get_reports($TorrentID);
    $NumReports = count($Reports);

    if ($NumReports > 0) {
        $Reported = true;
        include(SERVER_ROOT.'/sections/reportsv2/array.php');
        $ReportInfo = '
        <table class="reportinfo_table">
            <tr class="colhead_dark" style="font-weight: bold;">
                <td>This torrent has '.$NumReports.' active '.($NumReports === 1 ? 'report' : 'reports').":</td>
            </tr>";

        foreach ($Reports as $Report) {
            if (check_perms('admin_reports')) {
                $ReporterID = $Report['ReporterID'];
                $Reporter = Users::user_info($ReporterID);
                $ReporterName = $Reporter['Username'];
                $ReportLinks = "<a href=\"user.php?id=$ReporterID\">$ReporterName</a> <a href=\"reportsv2.php?view=report&amp;id=$Report[ID]\">reported it</a>";
            } else {
                $ReportLinks = 'Someone reported it';
            }

            if (isset($Types[$GroupCategoryID][$Report['Type']])) {
                $ReportType = $Types[$GroupCategoryID][$Report['Type']];
            } elseif (isset($Types['master'][$Report['Type']])) {
                $ReportType = $Types['master'][$Report['Type']];
            } else {
                //There was a type but it wasn't an option!
                $ReportType = $Types['master']['other'];
            }
            $ReportInfo .= "
            <tr>
                <td>$ReportLinks ".time_diff($Report['ReportedTime'], 2, true, true).' for the reason "'.$ReportType['title'].'":
                    <blockquote>'.Text::full_format($Report['UserComment']).'</blockquote>
                </td>
            </tr>';
        }
        $ReportInfo .= "\n\t\t</table>";
    }

    $CanEdit = (check_perms('torrents_edit') || (($UserID == $LoggedUser['ID'] && !$LoggedUser['DisableWiki']) && !($Remastered && !$RemasterYear)));

    $RegenLink = check_perms('users_mod') ? ' <a href="torrents.php?action=regen_filelist&amp;torrentid=' . $TorrentID . '" class="brackets">Regenerate</a>' : '';
    $FileTable = '
    <table class="filelist_table">
        <tr class="colhead_dark">
            <td>
                <div class="filelist_title" style="float: left;">File Names' . $RegenLink . '</div>
                <div class="filelist_path" style="float: right;">' . ($FilePath ? "/$FilePath/" : '') . '</div>
            </td>
            <td>
                <strong>Size</strong>
            </td>
        </tr>';
    if (substr($FileList, -3) == '}}}') { // Old style
        $FileListSplit = explode('|||', $FileList);
        foreach ($FileListSplit as $File) {
        $NameEnd = strrpos($File, '{{{');
        $Name = substr($File, 0, $NameEnd);
        if ($Spaces = strspn($Name, ' ')) {
            $Name = str_replace(' ', '&nbsp;', substr($Name, 0, $Spaces)) . substr($Name, $Spaces);
        }
        $FileSize = substr($File, $NameEnd + 3, -3);
        $FileTable .= sprintf("\n<tr><td>%s</td><td class=\"number_column\">%s</td></tr>", $Name, Format::get_size($FileSize));
        }
    } else {
        $FileListSplit = explode("\n", $FileList);
        foreach ($FileListSplit as $File) {
        $FileInfo = Torrents::filelist_get_file($File);
        $FileTable .= sprintf("\n<tr><td>%s</td><td class=\"number_column\">%s</td></tr>", $FileInfo['name'], Format::get_size($FileInfo['size']));
        }
    }
    $FileTable .= '
    </table>';

    $ExtraInfo = ''; // String that contains information on the torrent (e.g. format and encoding)
    $AddExtra = ''; // Separator between torrent properties

    // similar to Torrents::torrent_info()
    if ($Format) {
        $ExtraInfo .= display_str($Format);
        $AddExtra = ' / ';
    }
    if ($Encoding) {
        $ExtraInfo .= $AddExtra . display_str($Encoding);
        $AddExtra = ' / ';
    }
    if ($HasLog) {
        $ExtraInfo .= "{$AddExtra}Log";
        $AddExtra = ' / ';
    }
    if ($HasLog && $HasLogDB) {
        $ExtraInfo .= ' (' . (int)$LogScore . '%)';
    }
    if ($HasCue) {
        $ExtraInfo .= "{$AddExtra}Cue";
        $AddExtra = ' / ';
    }
    if ($Scene) {
        $ExtraInfo .= "{$AddExtra}Scene";
        $AddExtra = ' / ';
    }
    if (!$ExtraInfo) {
        $ExtraInfo = $GroupName;
        $AddExtra = ' / ';
    }
    if ($IsSnatched) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Snatched!');
        $AddExtra = ' / ';
    }
    if ($FreeTorrent == '1') {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Freeleech!');
        $AddExtra = ' / ';
    }
    if ($FreeTorrent == '2') {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Neutral Leech!');
        $AddExtra = ' / ';
    }
    if ($PersonalFL) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Personal Freeleech!');
        $AddExtra = ' / ';
    }
    if ($Reported) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Reported');
        $AddExtra = ' / ';
    }

    if ($HasLog && $HasLogDB && $LogChecksum !== '1') {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Bad/Missing Checksum');
        $AddExtra = ' / ';
    }

    if (!empty($BadTags)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Bad Tags');
        $AddExtra = ' / ';
    }
    if (!empty($BadFolders)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Bad Folders');
        $AddExtra = ' / ';
    }
    if (!empty($MissingLineage)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Missing Lineage');
        $AddExtra = ' / ';
    }
    if (!empty($CassetteApproved)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Cassette Approved');
        $AddExtra = ' / ';
    }
    if (!empty($LossymasterApproved)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Lossy Master Approved');
        $AddExtra = ' / ';
    }
    if (!empty($LossywebApproved)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Lossy WEB Approved');
        $AddExtra = ' / ';
    }
    if (!empty($BadFiles)) {
        $ExtraInfo .= $AddExtra . Format::torrent_label('Bad File Names');
        $AddExtra = ' / ';
    }

    if ($GroupCategoryID == 1
        && ($RemasterTitle != $LastRemasterTitle
        || $RemasterYear != $LastRemasterYear
        || $RemasterRecordLabel != $LastRemasterRecordLabel
        || $RemasterCatalogueNumber != $LastRemasterCatalogueNumber
        || $FirstUnknown
        || $Media != $LastMedia)) {

        $EditionID++;
?>
                <tr class="releases_<?=($ReleaseType)?> groupid_<?=($GroupID)?> edition group_torrent">
                    <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=($GroupID)?>, <?=($EditionID)?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($Torrent)?></strong></td>
                </tr>
<?php
    }

    $LastRemasterTitle = $RemasterTitle;
    $LastRemasterYear = $RemasterYear;
    $LastRemasterRecordLabel = $RemasterRecordLabel;
    $LastRemasterCatalogueNumber = $RemasterCatalogueNumber;
    $LastMedia = $Media;

        ?>
                <tr class="torrent_row releases_<?=($ReleaseType)?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> group_torrent<?=($IsSnatched ? ' snatched_torrent' : '')?>" style="font-weight: normal;" id="torrent<?=($TorrentID)?>">
                    <td>
                        <span>[ <a href="torrents.php?action=download&amp;id=<?=($TorrentID)?>&amp;authkey=<?=($LoggedUser['AuthKey'])?>&amp;torrent_pass=<?=($LoggedUser['torrent_pass'])?>" class="tooltip" title="Download"><?=($HasFile ? 'DL' : 'Missing')?></a>
<?php
    if (Torrents::can_use_token($Torrent)) { ?>
                            | <a href="torrents.php?action=download&amp;id=<?=($TorrentID)?>&amp;authkey=<?=($LoggedUser['AuthKey'])?>&amp;torrent_pass=<?=($LoggedUser['torrent_pass'])?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($Torrent['Seeders'], $Torrent['Size'])?>');">FL</a>
<?php
    } ?>
                            | <a href="reportsv2.php?action=report&amp;id=<?=($TorrentID)?>" class="tooltip" title="Report">RP</a>
<?php
    if ($CanEdit) { ?>
                            | <a href="torrents.php?action=edit&amp;id=<?=($TorrentID)?>" class="tooltip" title="Edit">ED</a>
<?php
    }
    if (check_perms('torrents_delete') || $UserID == $LoggedUser['ID']) { ?>
                            | <a href="torrents.php?action=delete&amp;torrentid=<?=($TorrentID)?>" class="tooltip" title="Remove">RM</a>
<?php
    } ?>
                            | <a href="torrents.php?torrentid=<?=($TorrentID)?>" class="tooltip" title="Permalink">PL</a>
                        ]</span>
                        &raquo; <a href="#" onclick="$('#torrent_<?=($TorrentID)?>').gtoggle(); return false;"><?=($ExtraInfo)?></a>
                    </td>
                    <td class="number_column nobr"><?=(Format::get_size($Size))?></td>
                    <td class="number_column"><?=(number_format($Snatched))?></td>
                    <td class="number_column"><?=(number_format($Seeders))?></td>
                    <td class="number_column"><?=(number_format($Leechers))?></td>
                </tr>
                <tr class="releases_<?=($ReleaseType)?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> torrentdetails pad<?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $TorrentID) { ?> hidden<?php } ?>" id="torrent_<?=($TorrentID)?>">
                    <td colspan="5">
                        <blockquote>
                            Uploaded by <?=(Users::format_username($UserID, false, false, false))?> <?=time_diff($TorrentTime);?>
<?php
    if ($Seeders == 0) {
        if ($LastActive != '0000-00-00 00:00:00' && time() - strtotime($LastActive) >= 1209600) { ?>
                                <br /><strong>Last active: <?=time_diff($LastActive);?></strong>
<?php   } else { ?>
                                <br />Last active: <?=time_diff($LastActive);?>
<?php   }
        if ($LastActive != '0000-00-00 00:00:00' && time() - strtotime($LastActive) >= 345678 && time() - strtotime($LastReseedRequest) >= 864000) { ?>
                                <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=($TorrentID)?>&amp;groupid=<?=($GroupID)?>" class="brackets">Request re-seed</a>
<?php   }
    } ?>
                        </blockquote>
<?php
    if (check_perms('site_moderate_requests')) { ?>
                        <div class="linkbox">
                            <a href="torrents.php?action=masspm&amp;id=<?=($GroupID)?>&amp;torrentid=<?=($TorrentID)?>" class="brackets">Mass PM snatchers</a>
                        </div>
<?php
    } ?>
                        <div class="linkbox">
                            <a href="#" class="brackets" onclick="show_peers('<?=($TorrentID)?>', 0); return false;">View peer list</a>
<?php
    if (check_perms('site_view_torrent_snatchlist')) { ?>
                            <a href="#" class="brackets tooltip" onclick="show_downloads('<?=($TorrentID)?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View download list</a>
                            <a href="#" class="brackets tooltip" onclick="show_snatches('<?=($TorrentID)?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatch list</a>
<?php
    } ?>
                            <a href="#" class="brackets" onclick="show_files('<?=($TorrentID)?>'); return false;">View file list</a>
<?php
    if ($Reported) { ?>
                            <a href="#" class="brackets" onclick="show_reported('<?=($TorrentID)?>'); return false;">View report information</a>
<?php
    } ?>
                        </div>
                        <div id="peers_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="downloads_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="snatches_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="files_<?=($TorrentID)?>" class="hidden"><?=($FileTable)?></div>
<?php
    if ($Reported) { ?>
                        <div id="reported_<?=($TorrentID)?>" class="hidden"><?=($ReportInfo)?></div>
<?php
    }
    if (!empty($Description)) {
        echo "\n\t\t\t\t\t\t<blockquote>" . Text::full_format($Description) . '</blockquote>';
    } ?>
                    </td>
                </tr>
<?php
    }
}
