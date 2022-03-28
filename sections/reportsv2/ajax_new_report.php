<?php
/*
 * This is the AJAX page that gets called from the JavaScript
 * function NewReport(), any changes here should probably be
 * replicated on static.php.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

[$ReportID, $ReporterID, $ReporterName, $TorrentID, $Type, $UserComment, $ResolverID, $ResolverName, $Status, $ReportedTime, $LastChangeTime,
    $ModComment, $Tracks, $Images, $ExtraIDs, $Links, $LogMessage, $GroupName, $GroupID, $ArtistID, $ArtistName, $Year, $CategoryID, $Time, $Remastered, $RemasterTitle,
    $RemasterYear, $Media, $Format, $Encoding, $Size, $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $UploaderID, $UploaderName
] = $DB->row("
    SELECT
        r.ID,
        r.ReporterID,
        reporter.Username,
        r.TorrentID,
        r.Type,
        r.UserComment,
        r.ResolverID,
        resolver.Username,
        r.Status,
        r.ReportedTime,
        r.LastChangeTime,
        r.ModComment,
        r.Track,
        r.Image,
        r.ExtraID,
        r.Link,
        r.LogMessage,
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
        t.HasCue,
        t.HasLogDB,
        t.LogScore,
        t.LogChecksum,
        t.UserID AS UploaderID,
        uploader.Username
    FROM reportsv2 AS r
    LEFT JOIN torrents AS t ON (t.ID = r.TorrentID)
    LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID AND ta.Importance = '1')
    LEFT JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)
    LEFT JOIN users_main AS resolver ON (resolver.ID = r.ResolverID)
    LEFT JOIN users_main AS reporter ON (reporter.ID = r.ReporterID)
    LEFT JOIN users_main AS uploader ON (uploader.ID = t.UserID)
    WHERE r.Status = 'New'
    GROUP BY r.ID
    ORDER BY ReportedTime ASC
    LIMIT 1
");
if (!$ReportID) {
    die();
}
$report = new Gazelle\ReportV2($ReportID);

if (!$GroupID) {
?>
<div id="report<?=$ReportID?>" class="report box pad center" data-reportid="<?=$ReportID?>">
<a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Report <?=$ReportID?></a> for torrent <?=$TorrentID?> (deleted) has been automatically resolved. <input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
</div>
<?php
    $report->resolve('Report already dealt with (torrent deleted)');
    (new Gazelle\Manager\Torrent)->findById($TorrentID)?->flush();
    exit;
}
$report->claim($Viewer->id());

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
if (array_key_exists($Type, $Types[$CategoryID])) {
    $ReportType = $Types[$CategoryID][$Type];
} elseif (array_key_exists($Type,$Types['master'])) {
    $ReportType = $Types['master'][$Type];
} else {
    //There was a type but it wasn't an option!
    $Type = 'other';
    $ReportType = $Types['master']['other'];
}
$RemasterDisplayString = $Remastered ? remasterInfo($RemasterTitle, $RemasterYear) : '';

if ($ArtistID == 0 && empty($ArtistName)) {
    $RawName = $GroupName.($Year ? " ($Year)" : '').($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '') . $RemasterDisplayString . ($HasCue ? ' (Cue)' : '').($HasLog ? " (Log".($HasLogDB ? ": {$LogScore}%" : '').')' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $LinkName = "<a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\">".($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '') . $RemasterDisplayString . '</a> '.($HasCue ? ' (Cue)' : '').($HasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log".($HasLogDB ? ": {$LogScore}%" : '').')</a>' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $BBName = "[url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLog ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log".($HasLogDB ? ": {$LogScore}%" : '').')[/url]' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
} elseif ($ArtistID == 0 && $ArtistName == 'Various Artists') {
    $RawName = "Various Artists - $GroupName".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]$RemasterDisplayString" . ($HasCue ? ' (Cue)' : '').($HasLog ? " (Log".($HasLogDB ? ": {$LogScore}%" : '').')' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $LinkName = "Various Artists - <a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\"> [$Format/$Encoding/$Media]$RemasterDisplayString</a> ".($HasCue ? ' (Cue)' : '').($HasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log".($HasLogDB ? ": {$LogScore}%" : '').')</a>' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $BBName = "Various Artists - [url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLog ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log".($HasLogDB ? ": {$LogScore}%" : '').')[/url]' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
} else {
    $RawName = "$ArtistName - $GroupName".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]$RemasterDisplayString" . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $LinkName = "<a href=\"artist.php?id=$ArtistID\">$ArtistName</a> - <a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\"> [$Format/$Encoding/$Media]$RemasterDisplayString</a> ".($HasCue ? ' (Cue)' : '').($HasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log".($HasLogDB ? ": {$LogScore}%" : '').')</a>' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
    $BBName = "[url=artist.php?id=$ArtistID]".$ArtistName."[/url] - [url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLog ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log".($HasLogDB ? ": {$LogScore}%" : '').')[/url]' : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
}
?>
<div id="report<?=$ReportID?>" class="report" data-reportid="<?=$ReportID?>">
    <form class="edit_form" name="report" id="reportform_<?=$ReportID?>" action="reports.php" method="post">
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
</div>
<table class="box layout" cellpadding="5">
    <tr>
        <td class="label"><a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Reported</a> torrent:</td>
        <td colspan="3">
<?php if (!$GroupID) { ?>
            <a href="log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
<?php } else { ?>
            <?=$LinkName?>
            <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
            uploaded by <a href="user.php?id=<?=$UploaderID?>"><?=$UploaderName?></a> <?=time_diff($Time)?>
            <br />
            <div style="text-align: right;">was reported by <a href="user.php?id=<?=$ReporterID?>"><?=$ReporterName?></a> <?=time_diff($ReportedTime)?> for the reason: <strong><?=$ReportType['title']?></strong></div>
<?php
    $totalGroup = $reportMan->totalReportsGroup($GroupID);
    if ($totalGroup > 1) {
        --$totalGroup;
?>
            <div style="text-align: right;">
                <a href="reportsv2.php?view=group&amp;id=<?=$GroupID?>">There <?= $totalGroup > 1
                    ? "are $totalGroup other reports" : "is 1 other report" ?> for torrents in this group</a>
            </div>
<?php
    }
    $totalUploader = $reportMan->totalReportsUploader($UploaderID);
    if ($totalUploader > 1) {
        --$totalUploader;
?>
            <div style="text-align: right;">
                <a href="reportsv2.php?view=uploader&amp;id=<?=$UploaderID?>">There <?= $totalUploader > 1
                    ? "are $totalUploader other reports" : "is 1 other report" ?> for torrents uploaded by this user</a>
            </div>
<?php
    }
    $DB->prepared_query("
        SELECT DISTINCT req.ID,
            req.FillerID,
            um.Username,
            req.TimeFilled
        FROM requests AS req
        INNER JOIN users_main AS um ON (um.ID = req.FillerID)
        LEFT JOIN torrents AS t ON (t.ID = req.TorrentID)
        LEFT JOIN reportsv2 AS rep ON (rep.TorrentID = t.ID)
        WHERE rep.Status != 'Resolved'
            AND req.TorrentID = ?
        ", $TorrentID
    );
    if ($DB->has_results() > 0) {
        while ([$RequestID, $FillerID, $FillerName, $FilledTime] = $DB->next_record()) {
?>
                <div style="text-align: right;">
                    <strong class="important_text"><a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=view&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?></strong>
                </div>
<?php   }
    }
}
?>
        </td>
    </tr>
<?php if ($Tracks) { ?>
    <tr>
        <td class="label">Relevant tracks:</td>
        <td colspan="3">
            <?=str_replace(' ', ', ', $Tracks)?>
        </td>
    </tr>
<?php
}

if ($Links) { ?>
    <tr>
        <td class="label">Relevant links:</td>
        <td colspan="3">
<?php
    $Links = explode(' ', $Links);
    foreach ($Links as $Link) {
        if ($local_url = Text::local_url($Link)) {
            $Link = $local_url;
        }
?>
            <a href="<?=$Link?>"><?=$Link?></a>
<?php } ?> </td>
    </tr>
<?php
}
if ($ExtraIDs) { ?>
    <tr>
        <td class="label">Relevant other torrents:</td>
        <td colspan="3">
<?php
    $First = true;
    $Extras = explode(' ', $ExtraIDs);
    foreach ($Extras as $ExtraID) {
        [$ExtraGroupName, $ExtraGroupID, $ExtraArtistID, $ExtraArtistName, $ExtraYear, $ExtraTime, $ExtraRemastered, $ExtraRemasterTitle,
            $ExtraRemasterYear, $ExtraMedia, $ExtraFormat, $ExtraEncoding, $ExtraSize, $ExtraHasLog, $ExtraHasCue, $ExtraHasLogDB, $ExtraLogScore, $ExtraLogChecksum,
            $ExtraUploaderID, $ExtraUploaderName
        ] = $DB->row("
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
                t.Time,
                t.Remastered,
                t.RemasterTitle,
                t.RemasterYear,
                t.Media,
                t.Format,
                t.Encoding,
                t.Size,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                t.UserID AS UploaderID,
                uploader.Username
            FROM torrents AS t
            LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
            LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID AND ta.Importance = '1')
            LEFT JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)
            LEFT JOIN users_main AS uploader ON (uploader.ID = t.UserID)
            WHERE t.ID = ?
            GROUP BY tg.ID
            ", $ExtraID
        );

        if ($ExtraGroupName) {
            $ExtraRemasterDisplayString = remasterInfo($ExtraRemastered, $ExtraRemasterTitle, $ExtraRemasterYear);
            if ($ArtistID == 0 && empty($ArtistName)) {
                $ExtraLinkName = "<a href=\"torrents.php?id=$ExtraGroupID\">display_str($ExtraGroupName)".($ExtraYear ? " ($ExtraYear)" : '')
                    . "</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> "
                    . ($ExtraHasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log"
                    . ($ExtraHasLogDB ? ": {$ExtraLogScore}%" : '').')</a>' : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
            } elseif ($ArtistID == 0 && $ArtistName == 'Various Artists') {
                $ExtraLinkName = "Various Artists - <a href=\"torrents.php?id=$ExtraGroupID\">$ExtraGroupName".($ExtraYear ? " ($ExtraYear)" : '')
                    . "</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> "
                    . ($ExtraHasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log"
                    . ($ExtraHasLogDB ? ": {$ExtraLogScore}%" : '').')</a>' : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
            } else {
                $ExtraLinkName = "<a href=\"artist.php?id=$ExtraArtistID\">$ExtraArtistName</a> - <a href=\"torrents.php?id=$ExtraGroupID\">$ExtraGroupName"
                    . ($ExtraYear ? " ($ExtraYear)" : '')."</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> "
                    . ($ExtraHasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log"
                    . ($ExtraHasLogDB ? ": {$ExtraLogScore}%" : '').')</a>' : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
            }
            $ExtraLinkName = display_str($ExtraLinkName);
?>
                <?=($First ? '' : '<br />')?><?=$ExtraLinkName?>
                <a href="torrents.php?action=download&amp;id=<?=$ExtraID?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
                uploaded by <a href="user.php?id=<?=$ExtraUploaderID?>"><?=$ExtraUploaderName?></a> <?=time_diff($ExtraTime)?> <a href="#" onclick="Switch(<?=$ReportID?>, <?=$TorrentID?>, <?=$ExtraID?>); return false;" class="brackets">Switch</a>
<?php
            $First = false;
        }
    } /* foreach */
?>
        </td>
    </tr>
<?php
}
if ($Images) { ?>
    <tr>
        <td class="label">Relevant images:</td>
        <td colspan="3">
<?php
    $Images = explode(' ', $Images);
    $imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
    foreach ($Images as $Image) {
?>
            <img style="max-width: 200px;" onclick="lightbox.init(this, 200);" src="<?= $imgProxy->process($Image) ?>" alt="Relevant image" />
<?php } ?>
        </td>
    </tr>
<?php } ?>
    <tr>
        <td class="label">User comment:</td>
        <td colspan="3"><?=Text::full_format($UserComment)?></td>
    </tr>
<?php                    /* END REPORTED STUFF :|: BEGIN MOD STUFF */ ?>
    <tr>
        <td class="label">Report comment:</td>
        <td colspan="3">
            <input type="text" name="comment" id="comment<?=$ReportID?>" size="70" value="<?= display_str($ModComment) ?>" />
            <input type="button" value="Update now" onclick="UpdateComment(<?=$ReportID?>);" />
        </td>
    </tr>
    <tr>
        <td class="label">
            <a href="javascript:Load('<?=$ReportID?>')" class="tooltip" title="Click here to reset the resolution options to their default values.">Resolve</a>:
        </td>
        <td colspan="3">
            <select name="resolve_type" id="resolve_type<?=$ReportID?>" onchange="ChangeResolve(<?=$ReportID?>);">
<?php
$TypeList = $Types['master'] + $Types[$CategoryID];
$Priorities = [];
foreach ($TypeList as $Key => $Value) {
    $Priorities[$Key] = $Value['priority'];
}
array_multisort($Priorities, SORT_ASC, $TypeList);

foreach ($TypeList as $Type => $Data) {
?>
                <option value="<?=$Type?>"><?=$Data['title']?></option>
<?php } ?>
            </select>
            <span id="options<?=$ReportID?>">
<?php if ($Viewer->permitted('users_mod')) { ?>
                <span class="tooltip" title="Delete torrent?">
                    <label for="delete<?=$ReportID?>"><strong>Delete</strong></label>
                    <input type="checkbox" name="delete" id="delete<?=$ReportID?>" />
                </span>
<?php } ?>
                <span class="tooltip" title="Warning length in weeks">
                    <label for="warning<?=$ReportID?>"><strong>Warning</strong></label>
                    <select name="warning" id="warning<?=$ReportID?>">
<?php for ($i = 0; $i < 9; $i++) { ?>
                        <option value="<?=$i?>"><?=$i?></option>
<?php } ?>
                    </select>
                </span>
                <span class="tooltip" title="Remove upload privileges?">
                    <label for="upload<?=$ReportID?>"><strong>Remove upload privileges</strong></label>
                    <input type="checkbox" name="upload" id="upload<?=$ReportID?>" />
                </span>
                &nbsp;&nbsp;
                <span class="tooltip" title="Update resolve type">
                    <input type="button" name="update_resolve" id="update_resolve<?=$ReportID?>" value="Update now" onclick="UpdateResolve(<?=$ReportID?>);" />
                </span>
            </span>
        </td>
    </tr>
    <tr>
        <td class="label tooltip" title="Uploader: Appended to the regular message unless using &quot;Send now&quot;. Reporter: Must be used with &quot;Send now&quot;.">
            PM
            <select name="pm_type" id="pm_type<?=$ReportID?>">
                <option value="Uploader">Uploader</option>
                <option value="Reporter">Reporter</option>
            </select>:
        </td>
        <td colspan="3">
            <textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="50" rows="1"></textarea>
            <input type="button" value="Send now" onclick="SendPM(<?=$ReportID?>);" />
        </td>
    </tr>
    <tr>
        <td class="label"><strong>Extra</strong> log message:</td>
        <td>
            <input type="text" name="log_message" id="log_message<?=$ReportID?>" size="40"<?php
                    if ($ExtraIDs) {
                        $Extras = explode(' ', $ExtraIDs);
                        $Value = '';
                        foreach ($Extras as $ExtraID) {
                            $Value .= "torrents.php?torrentid=$ExtraID ";
                        }
                        echo ' value="'.trim($Value).'"';
                    } ?> />
        </td>
        <td class="label"><strong>Extra</strong> staff notes:</td>
        <td>
            <input type="text" name="admin_message" id="admin_message<?=$ReportID?>" size="40" />
        </td>
    </tr>
    <tr>
        <td colspan="4" style="text-align: center;">
            <input type="button" value="Invalidate report" onclick="Dismiss(<?=$ReportID?>);" />
            <input type="button" value="Resolve report manually" onclick="ManualResolve(<?=$ReportID?>);" />
            | <input type="button" value="Unclaim" onclick="GiveBack(<?=$ReportID?>);" />
            | <input id="grab<?=$ReportID?>" type="button" value="Claim" onclick="Grab(<?=$ReportID?>);" />
            | Multi-resolve <input type="checkbox" name="multi" id="multi<?=$ReportID?>" checked="checked" />
            | <input type="button" value="Submit" onclick="TakeResolve(<?=$ReportID?>);" />
        </td>
    </tr>
</table>
</form>
</div>
