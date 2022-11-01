<?php

use Gazelle\Util\Time;

$TorrentID = (int)$_GET['torrentid'];
if (!$TorrentID) {
    error(404);
}

$torrent = (new Gazelle\Manager\Torrent)->findById($TorrentID);
if ($torrent->isUploadLocked()) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

if ($Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !$Viewer->permitted('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}

[$UserID, $Time, $Snatches] = $DB->row('
    SELECT
        t.UserID,
        t.Time,
        COUNT(x.uid)
    FROM torrents AS t
    LEFT JOIN xbt_snatched AS x ON (x.fid = t.ID)
    WHERE t.ID = ?
    GROUP BY t.UserID
    ', $TorrentID
);
if (!$UserID) {
    error('Torrent already deleted.');
}

if ($Viewer->id() != $UserID && !$Viewer->permitted('torrents_delete')) {
    error(403);
}

if (Time::timeAgo($Time) > 3600 * 24 * 7 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been uploaded for over a week. If you now think there is a problem, please report the torrent instead.');
}

if ($Snatches >= 5 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been snatched by 5 or more users. If you believe there is a problem with this torrent, please report it instead.');
}

View::show_header('Delete torrent', ['js' => 'reportsv2']);
?>
<div class="thin">
    <div class="box box2" style="width: 600px; margin-left: auto; margin-right: auto;">
        <div class="head colhead">
            Delete torrent
        </div>
        <div class="pad">
            <form class="delete_form" name="torrent" action="torrents.php" method="post">
                <input type="hidden" name="action" value="takedelete" />
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
                <div class="field_div">
                    <strong>Reason: </strong>
                    <select name="reason">
                        <option value="Dead">Dead</option>
                        <option value="Dupe">Dupe</option>
                        <option value="Trumped">Trumped</option>
                        <option value="MQA-encoded">MQA-encoded</option>
                        <option value="Rules Broken">Rules broken</option>
                        <option value="" selected="selected">Other</option>
                    </select>
                </div>
                <div class="field_div">
                    <strong>Extra info: </strong>
                    <input type="text" name="extra" size="30" />
                    <input value="Delete" type="submit" />
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($Viewer->permitted('admin_reports')) { ?>
<div id="all_reports" style="width: 80%; margin-left: auto; margin-right: auto;">
<?php
    $reportMan = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent);
    $Types = $reportMan->types();

    [$GroupName, $GroupID, $ArtistID, $ArtistName, $Year, $CategoryID,
        $Time, $Remastered, $RemasterTitle, $RemasterYear, $Media, $Format,
        $Encoding, $Size, $HasLog, $HasLogDB, $LogScore, $UploaderID] = $DB->row("
        SELECT
            tg.Name,
            tg.ID,
            CASE COUNT(ta.GroupID)
                WHEN 1 THEN aa.ArtistID
                WHEN 0 THEN '0'
                ELSE '0'
            END AS ArtistID,
            CASE COUNT(ta.GroupID)
                WHEN 1 THEN aa.Name
                WHEN 0 THEN ''
                ELSE 'Various Artists'
            END AS ArtistName,
            tg.Year,
            tg.CategoryID,
            t.Time,
            t.Remastered,
            t.RemasterTitle,
            t.RemasterYear,
            t.Media,
            t.Format,
            t.Encoding,
            t.Size,
            t.HasLog,
            t.HasLogDB,
            t.LogScore,
            t.UserID AS UploaderID
        FROM torrents AS t
        LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
        LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID AND ta.Importance = '1')
        LEFT JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)
        WHERE t.ID = ?
        ", $TorrentID
    );
    if (!$GroupName) {
        error("Torrent " . display_str($TorrentID) . " not found.");
    }
    $UploaderName = (new Gazelle\User($UploaderID))->username();

    $TypeList = $Types['master'];
    if (isset($Types[$CategoryID])) {
        $TypeList = array_merge($TypeList, $Types[$CategoryID]);
    }
    $Priorities = [];
    foreach ($TypeList as $Key => $Value) {
        $Priorities[$Key] = $Value['priority'];
    }
    array_multisort($Priorities, SORT_ASC, $TypeList);
    $ReportID = 0;
    $Type = 'dupe'; //hardcoded default

    if (array_key_exists($Type, $Types[$CategoryID])) {
        $ReportType = $Types[$CategoryID][$Type];
    } elseif (array_key_exists($Type,$Types['master'])) {
        $ReportType = $Types['master'][$Type];
    } else {
        //There was a type but it wasn't an option!
        $Type = 'other';
        $ReportType = $Types['master']['other'];
    }
    $Year = ($Year ? " ($Year)" : '');
    $mastering = ($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '')
        . ($Remastered ? remasterInfo($RemasterTitle, $RemasterYear) : '');
    $groupUrl = "torrents.php?id=$GroupID";
    $torrentUrl = "torrents.php?torrentid=$TorrentID";
    $viewLogUrl = "torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID";

    if ($ArtistID == 0 && empty($ArtistName)) {
        $RawName = $GroupName;
        $LinkName = "<a href=\"$groupUrl\">$GroupName" . $Year . "</a>";
        $BBName = "[url=$groupUrl]$GroupName" . $Year . "[/url]";
    } elseif ($ArtistID == 0 && $ArtistName == 'Various Artists') {
        $RawName = "Various Artists - $GroupName";
        $LinkName = "Various Artists - <a href=\"$groupUrl\">$GroupName" . $Year . "</a>";
        $BBName = "Various Artists - [url=$groupUrl]$GroupName" . $Year . "[/url]";
    } else {
        $RawName = "$ArtistName - $GroupName";
        $LinkName = "<a href=\"artist.php?id=$ArtistID\">$ArtistName</a> - <a href=\"$groupUrl\">$GroupName" . $Year . "</a>";
        $BBName = "[url=artist.php?id=$ArtistID]".$ArtistName."[/url] - [url=$groupUrl]$GroupName" . $Year . "[/url]";
    }
    $log = '(Log' . ($HasLogDB ? ": {$LogScore}%" : '') . ')';
    $sizeMB = ' (' . Format::get_size($Size) . ')';
    $RawName .= $Year . $mastering . ($HasLog ? " $log" : '') . $sizeMB;
    $LinkName .= " <a href=\"$torrentUrl\">" . $mastering . "</a>" . ($HasLog ? " <a href=\"$viewLogUrl\">$log</a>" : '') . $sizeMB;
    $BBName .= " [url={$torrentUrl}]" . $mastering . "[/url]" . ($HasLog ? (" [url=$viewLogUrl]{$log}[/url]") : '') . $sizeMB;

    $torMan = new Gazelle\Manager\Torrent;
    $userMan = new Gazelle\Manager\User;
?>

<div id="report<?=$ReportID?>" class="report">
    <form class="create_form" name="report" id="reportform_<?=$ReportID?>" action="reports.php" method="post">
<?php /* Some of these are for takeresolve, some for the JavaScript. */ ?>
        <div>
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" id="reportid<?=$ReportID?>" name="reportid" value="<?=$ReportID?>" />
            <input type="hidden" id="torrentid<?=$ReportID?>" name="torrentid" value="<?=$TorrentID?>" />
            <input type="hidden" id="uploader<?=$ReportID?>" name="uploader" value="<?=$UploaderName?>" />
            <input type="hidden" id="uploaderid<?=$ReportID?>" name="uploaderid" value="<?=$UploaderID?>" />
            <input type="hidden" id="reporterid<?=$ReportID?>" name="reporterid" value="<?=$ReporterID?>" />
            <input type="hidden" id="raw_name<?=$ReportID?>" name="raw_name" value="<?=$RawName?>" />
            <input type="hidden" id="type<?=$ReportID?>" name="type" value="<?=$Type?>" />
            <input type="hidden" id="categoryid<?=$ReportID?>" name="categoryid" value="<?=$CategoryID?>" />
            <input type="hidden" id="pm_type<?=$ReportID?>" name="pm_type" value="Uploader" />
            <input type="hidden" id="from_delete<?=$ReportID?>" name="from_delete" value="<?=$GroupID?>" />
        </div>
        <table cellpadding="5" class="box layout">
            <tr>
                <td class="label">Torrent:</td>
                <td colspan="3">
<?php if (!$GroupID) { ?>
                    <a href="log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
<?php } else { ?>
                    <?=$LinkName?>
                    <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" class="brackets tooltip" title="Download">DL</a>
                    uploaded by <a href="user.php?id=<?=$UploaderID?>"><?=$UploaderName?></a> <?=time_diff($Time)?>
                    <br />
<?php
        $torrent = $torMan->findById($TorrentID);
        $GroupOthers = $torrent->group()->unresolvedReportsTotal();
        if ($GroupOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=group&amp;id=<?=$GroupID?>">There <?= $GroupOthers > 1 ? "are $GroupOthers reports" : "is 1 other report" ?> for torrent(s) in this group</a>
                        </div>
<?php
}
        $UploaderOthers = (new Gazelle\Stats\User($UploaderID))->unresolvedReportsTotal();
        if ($UploaderOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=uploader&amp;id=<?=$UploaderID?>">There <?= $UploaderOthers > 1 ? "are $UploaderOthers reports" : "is 1 other report" ?> for torrent(s) uploaded by this user</a>
                        </div>
<?php
        }
        $requests = $torrent->requestFills();
        foreach ($requests as $r) {
            [$RequestID, $FillerID, $FilledTime] = $r;
            $FillerName = (new Gazelle\User($FillerID))->username();
?>
                        <div style="text-align: right;">
                            <strong class="important_text"><a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=viewrequest&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?></strong>
                        </div>
<?php
        }
    }
?>
                    </td>
                </tr>
<?php /* END REPORTED STUFF - BEGIN MOD STUFF */ ?>
                <tr>
                    <td class="label">
                        <a href="javascript:Load('<?=$ReportID?>')" class="tooltip" title="Click here to reset the resolution options to their default values.">Resolve:</a>
                    </td>
                    <td colspan="3">
                        <select name="resolve_type" id="resolve_type<?=$ReportID?>" onchange="ChangeResolve(<?=$ReportID?>);">
<?php foreach ($TypeList as $IType => $Data) { ?>
                            <option value="<?=$IType?>"<?=(($Type == $IType) ? ' selected="selected"' : '')?>><?=$Data['title']?></option>
<?php } ?>
                        </select>
                        <span id="options<?=$ReportID?>">
                            <span class="tooltip" title="Delete torrent?">
                                <label for="delete<?=$ReportID?>"><strong>Delete</strong></label>
                                <input type="checkbox" name="delete" id="delete<?=$ReportID?>"<?=($ReportType['resolve_options']['delete'] ? ' checked="checked"' : '')?> />
                            </span>
                            <span class="tooltip" title="Warning length in weeks">
                                <label for="warning<?=$ReportID?>"><strong>Warning</strong></label>
                                <select name="warning" id="warning<?=$ReportID?>">
<?php for ($i = 0; $i < 9; $i++) { ?>
                                    <option value="<?=$i?>"<?=(($ReportType['resolve_options']['warn'] == $i) ? ' selected="selected"' : '')?>><?=$i?></option>
<?php } ?>
                                </select>
                            </span>
                            <span class="tooltip" title="Remove upload privileges?">
                                <label for="upload<?=$ReportID?>"><strong>Remove upload privileges</strong></label>
                                <input type="checkbox" name="upload" id="upload<?=$ReportID?>"<?=($ReportType['resolve_options']['upload'] ? ' checked="checked"' : '')?> />
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="label">PM uploader:</td>
                    <td colspan="3">
                        <span class="tooltip" title="Appended to the regular message unless using &quot;Send now&quot;.">
                            <textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="50" rows="1"></textarea>
                        </span>
                        <input type="button" value="Send now" onclick="SendPM(<?=$ReportID?>);" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Extra</strong> log message:</td>
                    <td>
                        <input type="text" name="log_message" id="log_message<?=$ReportID?>" size="40" />
                    </td>
                    <td class="label"><strong>Extra</strong> staff notes:</td>
                    <td>
                        <input type="text" name="admin_message" id="admin_message<?=$ReportID?>" size="40" />
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: center;">
                        <input type="button" value="Submit" onclick="TakeResolve(<?=$ReportID?>);" />
                    </td>
                </tr>
            </table>
        </form>
        <br />
    </div>
</div>
<?php
} /* $Viewer->permitted('admin_reports') */
View::show_footer();
