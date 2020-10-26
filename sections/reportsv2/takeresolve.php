<?php
/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error. If you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

if (!check_perms('admin_reports')) {
    error(403);
}
authorize();


//If we're here from the delete torrent page instead of the reports page.
if (!isset($_POST['from_delete'])) {
    $Report = true;
} elseif (!is_number($_POST['from_delete'])) {
    error("error from_delete");
} else {
    $Report = false;
}

$PMMessage = $_POST['uploader_pm'];

if ($_POST['pm_type'] != 'Uploader') {
    $_POST['uploader_pm'] = '';
}

$UploaderID = (int)$_POST['uploaderid'];
if (!is_number($UploaderID)) {
    error("error uploader id");
}

$Warning = (int)$_POST['warning'];
if (!is_number($Warning)) {
    error("error warning id");
}

$CategoryID = $_POST['categoryid'];
if (!isset($CategoryID)) {
    error("error category id");
}

if (is_number($_POST['reportid'])) {
    $ReportID = $_POST['reportid'];
} else {
    error("error report id");
}

$report = new Gazelle\ReportV2($ReportID);
$TorrentID = $_POST['torrentid'];
$RawName = $_POST['raw_name'];

if (isset($_POST['delete']) && $Cache->get_value("torrent_$TorrentID".'_lock')) {
    error("You requested to delete the torrent $TorrentID, but this is currently not possible because the upload process is still running. Please try again later.");
}

if (($_POST['resolve_type'] == 'manual' || $_POST['resolve_type'] == 'dismiss') && $Report) {
    if ($_POST['comment']) {
        $Comment = $_POST['comment'];
    } else {
        if ($_POST['resolve_type'] == 'manual') {
            $Comment = 'Report was resolved manually.';
        } elseif ($_POST['resolve_type'] == 'dismiss') {
             $Comment = 'Report was dismissed as invalid.';
        }
    }

    if ($report->moderatorResolve($LoggedUser['ID'], $Comment)) {
        $Cache->deleteMulti(['num_torrent_reportsv2', "reports_torrent_$TorrentID"]);
    } else {
        //Someone beat us to it. Inform the staffer.
?>
    <table class="layout" cellpadding="5">
        <tr>
            <td>
                <a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Somebody has already resolved this report</a>
                <input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
            </td>
        </tr>
    </table>
<?php
    }
    die();
}

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
if (!isset($_POST['resolve_type'])) {
    error("No Resolve Type");
} elseif (array_key_exists($_POST['resolve_type'], $Types[$CategoryID])) {
    $ResolveType = $Types[$CategoryID][$_POST['resolve_type']];
} elseif (array_key_exists($_POST['resolve_type'], $Types['master'])) {
    $ResolveType = $Types['master'][$_POST['resolve_type']];
} else {
    //There was a type but it wasn't an option!
    error("Invalid Resolve Type");
}

$GroupID = $DB->scalar("
    SELECT GroupID FROM torrents WHERE ID = ?
    ", $TorrentID
);
if (!$GroupID) {
    $report->moderatorResolve($LoggedUser['ID'], 'Report already dealt with (torrent deleted).');
    $Cache->decrement('num_torrent_reportsv2');
}

$check = false;
if ($Report) {
    $check = $report->moderatorResolve($LoggedUser['ID'], '');
}

//See if it we managed to resolve
if (!($check || !$Report)) {
    // Someone beat us to it. Inform the staffer.
?>
<a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Somebody has already resolved this report</a>
<input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
<?php
} else {
    //We did, lets do all our shit
    if ($Report) {
        $Cache->decrement('num_torrent_reportsv2');
    }

    if (isset($_POST['upload'])) {
        $Upload = true;
    } else {
        $Upload = false;
    }

    if ($_POST['resolve_type'] === 'tags_lots') {
        $DB->prepared_query("
            INSERT IGNORE INTO torrents_bad_tags
                   (TorrentID, UserID)
            VALUES (?,         ?)
            ", $TorrentID, $LoggedUser['ID']
        );
        $Cache->delete_value("torrents_details_$GroupID");
        $SendPM = true;
    }
    elseif ($_POST['resolve_type'] === 'folders_bad') {
        $DB->prepared_query("
            INSERT IGNORE INTO torrents_bad_folders
                   (TorrentID, UserID)
            VALUES (?,         ?)
            ", $TorrentID, $LoggedUser['ID']
        );
        $Cache->delete_value("torrents_details_$GroupID");
        $SendPM = true;
    }
    elseif ($_POST['resolve_type'] === 'filename') {
        $DB->prepared_query("
            INSERT IGNORE INTO torrents_bad_files
                   (TorrentID, UserID)
            VALUES (?,         ?)
            ", $TorrentID, $LoggedUser['ID']
        );
        $Cache->delete_value("torrents_details_$GroupID");
        $SendPM = true;
    }
    elseif ($_POST['resolve_type'] === 'lineage') {
        $DB->prepared_query("
            INSERT IGNORE INTO torrents_missing_lineage
                   (TorrentID, UserID)
            VALUES (?,         ?)
            ", $TorrentID, $LoggedUser['ID']
        );
        $Cache->delete_value("torrents_details_$GroupID");
    }
    elseif ($_POST['resolve_type'] === 'lossyapproval') {
        $DB->prepared_query("
            INSERT IGNORE INTO torrents_lossymaster_approved
                   (TorrentID, UserID)
            VALUES (?,         ?)
            ", $TorrentID, $LoggedUser['ID']
        );
        $Cache->delete_value("torrents_details_$GroupID");
    }

    //Log and delete
    if (!(isset($_POST['delete']) && check_perms('users_mod'))) {
        $Log = "No log message (torrent wasn't deleted).";
    } else {
        $UpUsername = $DB->scalar("
            SELECT Username
            FROM users_main
            WHERE ID = ?
            ", $UploaderID
        );
        $Log = "Torrent $TorrentID ($RawName) uploaded by $UpUsername was deleted by ".$LoggedUser['Username']
            . ($_POST['resolve_type'] == 'custom' ? '' : ' for the reason: '.$ResolveType['title'].".");
        if (isset($_POST['log_message']) && $_POST['log_message'] != '') {
            $Log .= ' (' . trim($_POST['log_message']) . ')';
        }
        $InfoHash = $DB->scalar("
            SELECT info_hash
            FROM torrents
            WHERE ID = ?
            ", $TorrentID
        );
        (new Gazelle\Manager\Torrent)
            ->setTorrentId($TorrentID)
            ->setViewer($LoggedUser['ID'])
            ->remove(
                sprintf('%s (%s)', $ResolveType['title'], $Escaped['log_message'] ?? 'none'),
                $ResolveType['reason']
            );

        $TrumpID = 0;
        if ($_POST['resolve_type'] === 'trump') {
            if (preg_match('/torrentid=([0-9]+)/', $_POST['log_message'], $Matches) === 1) {
                $TrumpID = $Matches[1];
            }
        }

        Torrents::send_pm($TorrentID, $UploaderID, $RawName, $Log, $TrumpID, (!$_POST['uploader_pm'] && $Warning <= 0 && !isset($_POST['delete']) && !$SendPM));
    }

    //Warnings / remove upload
    if ($Upload) {
        $Cache->delete_value("user_info_heavy_$UploaderID");
        $DB->prepared_query("
            UPDATE users_info SET
                DisableUpload = '1'
            WHERE UserID = ?
            ", $UploaderID
            );
    }

    if ($Warning > 0) {
        $WarnLength = $Warning * (7 * 24 * 60 * 60);
        $Reason = "Uploader of torrent ($TorrentID) $RawName which was resolved with the preset: ".$ResolveType['title'].'.';
        if ($_POST['admin_message']) {
            $Reason .= ' (' . trim($_POST['admin_message']) . ').';
        }
        if ($Upload) {
            $Reason .= ' (Upload privileges removed).';
        }

        Tools::warn_user($UploaderID, $WarnLength, $Reason);
    } else {
        //This is a bitch for people that don't warn but do other things, it makes me sad.
        $AdminComment = '';
        if ($Upload) {
            //They removed upload
            $AdminComment .= 'Upload privileges removed by '.$LoggedUser['Username'];
            $AdminComment .= "\nReason: Uploader of torrent ($TorrentID) " . $RawName
                . ' which was resolved with the preset: ' . $ResolveType['title'] . ". (Report ID: $ReportID)";
        }
        if ($_POST['admin_message']) {
            //They did nothing of note, but still want to mark it (Or upload and mark)
            $AdminComment .= ' (' . trim($_POST['admin_message']) . ')';
        }
        if ($AdminComment) {
            $DB->prepared_query("
                UPDATE users_info
                SET AdminComment = CONCAT(?, AdminComment)
                WHERE UserID = ?
                ", date('Y-m-d') . " - $AdminComment\n\n", $UploaderID
            );
        }
    }

    //PM
    if ($_POST['uploader_pm'] || $Warning > 0 || isset($_POST['delete']) || $SendPM) {
        if (isset($_POST['delete'])) {
            $PM = '[url='.site_url()."torrents.php?torrentid=$TorrentID]Your above torrent[/url] was reported and has been deleted.\n\n";
        } else {
            $PM = '[url='.site_url()."torrents.php?torrentid=$TorrentID]Your above torrent[/url] was reported but not deleted.\n\n";
        }

        $Preset = $ResolveType['resolve_options']['pm'];

        if ($Preset != '') {
             $PM .= "Reason: $Preset\n\n";
        }

        if ($Warning > 0) {
            $PM .= "This has resulted in a [url=".site_url()."wiki.php?action=article&amp;name=warnings]$Warning week warning.[/url]\n\n";
        }

        if ($Upload) {
            $PM .= 'This has '.($Warning > 0 ? 'also ' : '')."resulted in the loss of your upload privileges.\n\n";
        }

        if ($Log) {
            $PM .= "Log Message: $Log\n\n";
        }

        if ($_POST['uploader_pm']) {
            $PM .= "Message from ".$LoggedUser['Username'].": $PMMessage\n\n";
        }

        $PM .= "Report was handled by [user]".$LoggedUser['Username'].'[/user].';

        Misc::send_pm($UploaderID, 0, $_POST['raw_name'], $PM);
    }

    $Cache->delete_value("reports_torrent_$TorrentID");

    // Now we've done everything, update the DB with values
    if ($Report) {
        $report->finalize($_POST['resolve_type'], $Log, $_POST['comment']);
    }
}
