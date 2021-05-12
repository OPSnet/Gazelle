<?php
/*
 * This page is used for viewing reports in every viewpoint except auto.
 * It doesn't AJAX grab a new report when you resolve each one, use auto
 * for that (reports.php). If you wanted to add a new view, you'd simply
 * add to the case statement(s) below and add an entry to views.php to
 * explain it.
 * Any changes made to this page within the foreach loop should probably be
 * replicated on the auto page (reports.php).
 */

if (!check_perms('admin_reports')) {
    error(403);
}

$reportMan = new Gazelle\Manager\ReportV2;
$userMan = new Gazelle\Manager\User;
$Types = $reportMan->types();

$View = $_GET['view'];
if (isset($_GET['id'])) {
    if ((int)$_GET['id'] > 0) {
        $ID = (int)$_GET['id'];
    } else {
        $reportType = $_GET['id'];
        $ID = 0;
    }
}

$orderBy = 'ORDER BY r.ReportedTime ASC';
$cond = [];
$args = [];
if (!$ID) {
    switch ($View) {
        case 'resolved':
            $Title = 'Resolved reports';
            $cond[] = "r.Status = 'Resolved'";
            $orderBy = 'ORDER BY r.LastChangeTime DESC';
            break;
        case 'unauto':
            $Title = 'New reports, not auto assigned!';
            $cond[] = "r.Status = 'New'";
            break;
        case 'type':
            $reportName = $reportMan->typeName($reportType);
            $Title = "All new reports of type $reportName";
            $cond[] = "r.Status = 'New' AND r.Type = ?";
            $args[] = $reportType;
            break;
        default:
            error(404);
            break;
    }
} else {
    $Username = $DB->scalar("
        SELECT Username FROM users_main WHERE ID = ?
        ", $ID
    );
    switch ($View) {
        case 'staff':
            $Title = ($Username ?: $ID) . "'s in-progress reports";
            $cond[] = "r.Status = 'InProgress' AND r.ResolverID = ?";
            $args[] = $ID;
            break;
        case 'resolver':
            $Title = ($Username ?: $ID) . "'s resolved reports";
            $cond[] = "r.Status = 'Resolved' AND r.ResolverID = ?";
            $args[] = $ID;
            $orderBy = 'ORDER BY r.LastChangeTime DESC';
            break;
        case 'group':
            $Title = "Unresolved reports for the group $ID";
            $cond[] = "r.Status != 'Resolved' AND tg.ID = ?";
            $args[] = $ID;
            break;
        case 'torrent':
            $Title = "All reports for the torrent $ID";
            $cond[] = 'r.TorrentID = ?';
            $args[] = $ID;
            break;
        case 'report':
            $Title = "Viewing resolution of report $ID";
            $cond[] = 'r.ID = ?';
            $args[] = $ID;
            break;
        case 'reporter':
            $Title = 'All torrents reported by ' . ($Username ?: "user $ID");
            $cond[] = 'r.ReporterID = ?';
            $args[] = $ID;
            $orderBy = 'ORDER BY r.ReportedTime DESC';
            break;
        case 'uploader':
            $Title = 'All torrents uploaded by ' . ($Username ?: "user $ID");
            $cond[] = "r.Status != 'Resolved' AND t.UserID = ?";
            $args[] = $ID;
            break;
        default:
            error(404);
            break;
    }
}

$tables = "
    reportsv2 AS r
    LEFT JOIN torrents AS t ON (t.ID = r.TorrentID)
    LEFT JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
    LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)";

$Results = $DB->scalar("
    SELECT count(*) FROM $tables WHERE
    " . implode("\n    AND ", $cond), ...$args
);
$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($Results);

$DB->prepared_query("
    SELECT r.ID,
        r.ReporterID,
        r.TorrentID,
        r.Type,
        r.UserComment,
        r.ResolverID,
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
        t.GroupID,
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
        t.Description,
        t.FileList,
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
        tls.last_action,
        t.UserID AS UploaderID
    FROM $tables
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID AND ta.Importance = '1')
    LEFT JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)
    WHERE " . implode("\n    AND ", $cond) . "
    GROUP BY r.ID
    $orderBy
    LIMIT ? OFFSET ?
    ", ...array_merge($args, [$paginator->limit(), $paginator->offset()])
);
$Reports = $DB->to_array(false, MYSQLI_NUM);

View::show_header('Reports V2', 'reportsv2,bbcode,torrent');
?>
<div class="header">
    <h2><?=$Title?></h2>
<?php require_once('header.php'); ?>
</div>
<div class="buttonbox pad center">
<?php if ($View !== 'resolved') { ?>
    <span class="tooltip" title="Resolves *all* checked reports with their respective resolutions"><input type="button" onclick="MultiResolve();" value="Multi-resolve" /></span>
    <span class="tooltip" title="Assigns all of the reports on the page to you!"><input type="button" onclick="Grab();" value="Claim all" /></span>
<?php
}
if ($View === 'staff' && $LoggedUser['ID'] == $ID) { ?>
    | <span class="tooltip" title="Unclaim all of the reports currently displayed"><input type="button" onclick="GiveBack();" value="Unclaim all" /></span>
<?php } ?>
</div>
<?= $paginator->linkbox() ?>
<div id="all_reports" style="width: 80%; margin-left: auto; margin-right: auto;">
<?php if (count($Reports) === 0) { ?>
    <div class="box pad center">
        <strong>No new reports! \o/</strong>
    </div>
<?php
} else {
    $ripFiler = new Gazelle\File\RipLog;
    foreach ($Reports as $Report) {

        [$ReportID, $ReporterID, $TorrentID, $Type, $UserComment, $ResolverID,
            $Status, $ReportedTime, $LastChangeTime, $ModComment, $Tracks, $Images,
            $ExtraIDs, $Links, $LogMessage, $GroupName, $GroupID, $ArtistID, $ArtistName, $Year,
            $CategoryID, $Time, $Description, $FileList, $Remastered, $RemasterTitle, $RemasterYear,
            $Media, $Format, $Encoding, $Size, $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum,
            $LastAction, $UploaderID
        ] = $Report;
        $reporterName = (int)$ReporterID ? $userMan->findById((int)$ReporterID)->username() : 'System';
        $uploaderName = (int)$UploaderID ? $userMan->findById((int)$UploaderID)->username() : 'System';
        $resolverName = (int)$ResolverID ? $userMan->findById((int)$ResolverID)->username() : 'System';
        $ModComment = display_str($ModComment);
        $report = new Gazelle\ReportV2($ReportID);

        if (!$GroupID && $Status != 'Resolved') {
            //Torrent already deleted
            $report->resolve('Report already dealt with (torrent deleted)');
?>
    <div id="report<?=$ReportID?>" class="report box pad center">
        <a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Report <?=$ReportID?></a> for torrent <?=$TorrentID?> (deleted) has been automatically resolved. <input type="button" value="Hide" onclick="ClearReport(<?=$ReportID?>);" />
    </div>
<?php
        } else {
            if (!$CategoryID) {
                //Torrent was deleted
            } else {
                $ReportType = $reportMan->type($Type);
            }
            $RemasterDisplayString = $Remastered ? remasterInfo($RemasterTitle, $RemasterYear) : '';

            if ($ArtistID == 0 && empty($ArtistName)) {
                $RawName = $GroupName.($Year ? " ($Year)" : '').($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '') . $RemasterDisplayString . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';

                $LinkName = "<a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\">".($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '') . $RemasterDisplayString . '</a> '.($HasCue ? ' (Cue)' : '').($HasLog ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log: {$LogScore}%)</a>" : '').' ('.number_format($Size / (1024 * 1024), 2)." MiB)";

                $BBName = "[url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLog ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log: {$LogScore}%)[/url]" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
            } elseif ($ArtistID == 0 && $ArtistName == 'Various Artists') {
                $RawName = "Various Artists - $GroupName".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]{$RemasterDisplayString}" . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';

                $LinkName = "Various Artists - <a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\"> [$Format/$Encoding/$Media]$RemasterDisplayString</a> ".($HasCue ? ' (Cue)' : '').($HasLogDB ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log: {$LogScore}%)</a>" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';

                $BBName = "Various Artists - [url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLogDB ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log: {$LogScore}%)[/url]" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
            } else {
                $RawName = "$ArtistName - $GroupName".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]{$RemasterDisplayString}" . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';

                $LinkName = "<a href=\"artist.php?id=$ArtistID\">$ArtistName</a> - <a href=\"torrents.php?id=$GroupID\">$GroupName".($Year ? " ($Year)" : '')."</a> <a href=\"torrents.php?torrentid=$TorrentID\"> [$Format/$Encoding/$Media]{$RemasterDisplayString}</a> ".($HasCue ? ' (Cue)' : '').($HasLogDB ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID\">(Log: {$LogScore}%)</a>" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';

                $BBName = "[url=artist.php?id=$ArtistID]".$ArtistName."[/url] - [url=torrents.php?id=$GroupID]$GroupName".($Year ? " ($Year)" : '')."[/url] [url=torrents.php?torrentid=$TorrentID][$Format/$Encoding/$Media]{$RemasterDisplayString}[/url] ".($HasCue ? ' (Cue)' : '').($HasLogDB ? " [url=torrents.php?action=viewlog&amp;torrentid=$TorrentID&amp;groupid=$GroupID](Log: {$LogScore}%)[/url]" : '').' ('.number_format($Size / (1024 * 1024), 2).' MiB)';
            }
?>
    <div id="report<?=$ReportID?>">
        <form class="manage_form" name="report" id="reportform_<?=$ReportID?>" action="reports.php" method="post">
<?php
/*
* Some of these are for takeresolve, namely the ones that aren't inputs, some for the JavaScript.
*/
?>
            <div>
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" id="reportid<?=$ReportID?>" name="reportid" value="<?=$ReportID?>" />
                <input type="hidden" id="torrentid<?=$ReportID?>" name="torrentid" value="<?=$TorrentID?>" />
                <input type="hidden" id="uploader<?=$ReportID?>" name="uploader" value="<?= $uploaderName ?>" />
                <input type="hidden" id="uploaderid<?=$ReportID?>" name="uploaderid" value="<?=$UploaderID?>" />
                <input type="hidden" id="reporterid<?=$ReportID?>" name="reporterid" value="<?=$ReporterID?>" />
                <input type="hidden" id="report_reason<?=$ReportID?>" name="report_reason" value="<?=$UserComment?>" />
                <input type="hidden" id="raw_name<?=$ReportID?>" name="raw_name" value="<?=$RawName?>" />
                <input type="hidden" id="type<?=$ReportID?>" name="type" value="<?=$Type?>" />
                <input type="hidden" id="categoryid<?=$ReportID?>" name="categoryid" value="<?=$CategoryID?>" />
            </div>
            <table class="box layout" cellpadding="5">
                <tr>
                    <td class="label"><a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Reported</a> torrent:</td>
                    <td colspan="3">
<?php       if (!$GroupID) { ?>
                        <a href="log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
<?php       } else { ?>
                        <?=$LinkName?>
                        <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download" class="brackets tooltip">DL</a>
                        <br /><span class="report_reporter">reported by <a href="user.php?id=<?=$ReporterID?>"><?= $reporterName ?></a> <?=time_diff($ReportedTime)?> for the reason: <strong><?=$ReportType['title']?></strong></span>
                        <br />uploaded by <a href="user.php?id=<?=$UploaderID?>"><?= $uploaderName  ?></a> on <span title="<?= time_diff($Time, 3, false) ?>"><?= $Time ?></span>
                        <br />Last action: <?= $LastAction ?: 'Never' ?>
                        <br /><span class="report_torrent_file_ext">Audio files present:
<?php                   $extMap = audio_file_map($FileList);
                        if (count($extMap) == 0) {
?>
                            <span class="file_ext_none">none</span>
<?php                   } else { ?>
                            <span class="file_ext_map"><?= implode(', ', array_map(function ($x) use ($extMap) { return "$x:" . $extMap[$x]; }, array_keys($extMap))) ?></span>
<?php                   } ?>
                        </span>
<?php                   if (strlen($Description)) { ?>
                        <br /><span class="report_torrent_info" title="Release description of reported torrent">Release info: <?= Text::full_format($Description) ?></span>
<?php                   } ?>

<?php           if ($Status != 'Resolved') {
                    $totalGroup = $reportMan->totalReportsGroup($GroupID);
                    if ($totalGroup > 1) {
                        --$totalGroup;
 ?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=group&amp;id=<?=$GroupID?>">There <?=
                                $totalGroup > 1 ? "are $totalGroup other reports" : "is 1 other report"
                                ?> for torrent(s) in this group</a>
                        </div>
<?php
                    }
                    $totalUploaded = $reportMan->totalReportsUploader($UploaderID);
                    if ($totalUploaded > 1) {
                        --$totalUploaded;
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=uploader&amp;id=<?=$UploaderID?>">There <?=
                                $totalUploaded > 1 ? "are $totalUploaded other reports" : "is 1 other report"
                                ?> for torrent(s) uploaded by this user</a>
                        </div>
<?php               }

                    $DB->prepared_query("
                        SELECT DISTINCT req.ID,
                            req.FillerID,
                            um.Username,
                            req.TimeFilled
                        FROM requests AS req
                            LEFT JOIN torrents AS t ON t.ID = req.TorrentID
                            LEFT JOIN reportsv2 AS rep ON rep.TorrentID = t.ID
                            JOIN users_main AS um ON um.ID = req.FillerID
                        WHERE rep.Status != 'Resolved'
                            AND req.TorrentID = ?
                        ", $TorrentID
                    );
                    if ($DB->has_results()) {
                        while ([$RequestID, $FillerID, $FillerName, $FilledTime] = $DB->next_record()) {
?>
                        <div style="text-align: right;">
                            <strong class="important_text"><a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=view&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?></strong>
                        </div>
<?php                   }
                    }
                }
            }
?>
                    </td>
                </tr>
<?php       if ($Tracks) { ?>
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
<?php           } ?>
                    </td>
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
                    $DB->prepared_query("
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
                            t.Description,
                            t.Filelist,
                            t.Remastered,
                            t.RemasterTitle,
                            t.RemasterYear,
                            t.Media,
                            t.Format,
                            t.Encoding,
                            t.Size,
                            t.HasCue,
                            t.HasLog,
                            t.LogScore,
                            tls.last_action,
                            t.UserID AS UploaderID,
                            uploader.Username
                        FROM torrents AS t
                        LEFT JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
                        LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                        LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID AND ta.Importance = '1')
                        LEFT JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)
                        LEFT JOIN users_main AS uploader ON (uploader.ID = t.UserID)
                        WHERE t.ID = ?
                        GROUP BY tg.ID
                        ", $ExtraID
                    );

                    [$ExtraGroupName, $ExtraGroupID, $ExtraArtistID, $ExtraArtistName,
                        $ExtraYear, $ExtraTime, $ExtraDescription, $ExtraFileList, $ExtraRemastered,
                        $ExtraRemasterTitle, $ExtraRemasterYear, $ExtraMedia, $ExtraFormat,
                        $ExtraEncoding, $ExtraSize, $ExtraHasCue, $ExtraHasLog, $ExtraLogScore,
                        $ExtraLastAction, $ExtraUploaderID, $ExtraUploaderName]
                            = $DB->next_record();

                    if ($ExtraGroupName) {
                        $ExtraGroupName = display_str($ExtraGroupName);
                        $ExtraArtistName = display_str($ExtraArtistName);
                        $ExtraDescription = display_str($ExtraDescription);
                        $ExtraFileList = display_str($ExtraFileList);
                        $ExtraRemasterTitle = display_str($ExtraRemasterTitle);
                        $ExtraUploaderName = display_str($ExtraUploaderName);
                        $ExtraRemasterDisplayString = $ExtraRemastered ? remasterInfo($ExtraRemasterTitle, $ExtraRemasterYear) : '';

                        if ($ArtistID == 0 && empty($ArtistName)) {
                            $ExtraLinkName = "<a href=\"torrents.php?id=$ExtraGroupID\">$ExtraGroupName".($ExtraYear ? " ($ExtraYear)" : '')."</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> " . ($ExtraHasCue == '1' ? ' (Cue)' : '').($ExtraHasLog == '1' ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log: {$ExtraLogScore}%)</a>" : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
                        } elseif ($ArtistID == 0 && $ArtistName == 'Various Artists') {
                            $ExtraLinkName = "Various Artists - <a href=\"torrents.php?id=$ExtraGroupID\">$ExtraGroupName".($ExtraYear ? " ($ExtraYear)" : '')."</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> " . ($ExtraHasCue == '1' ? ' (Cue)' : '').($ExtraHasLog == '1' ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log: {$ExtraLogScore}%)</a>" : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
                        } else {
                            $ExtraLinkName = "<a href=\"artist.php?id=$ExtraArtistID\">$ExtraArtistName</a> - <a href=\"torrents.php?id=$ExtraGroupID\">$ExtraGroupName".($ExtraYear ? " ($ExtraYear)" : '')."</a> <a href=\"torrents.php?torrentid=$ExtraID\"> [$ExtraFormat/$ExtraEncoding/$ExtraMedia]$ExtraRemasterDisplayString</a> " . ($ExtraHasCue == '1' ? ' (Cue)' : '').($ExtraHasLog == '1' ? " <a href=\"torrents.php?action=viewlog&amp;torrentid=$ExtraID&amp;groupid=$ExtraGroupID\">(Log: {$ExtraLogScore}%)</a>" : '').' ('.number_format($ExtraSize / (1024 * 1024), 2).' MiB)';
                        }
?>
                        <?=($First ? '' : '<br />')?>
                        <?=$ExtraLinkName?>
                        <a href="torrents.php?action=download&amp;id=<?=$ExtraID?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download" class="brackets tooltip">DL</a>
                        <br />uploaded by <a href="user.php?id=<?=$ExtraUploaderID?>"><?=$ExtraUploaderName?></a> on <span title="<?=
                            time_diff($ExtraTime, 3, false) ?>"><?= $ExtraTime ?> (<?=
                            strtotime($ExtraTime) < strtotime($Time) ? 'older upload' : 'more recent upload' ?>)</span>
                        <br />Last action: <?= $ExtraLastAction ?: 'Never' ?>
                        <br /><span>Audio files present:
<?php
                        $First = false;
                        $extMap = audio_file_map($ExtraFileList);
                        if (count($extMap) == 0) {
?>
                            <span class="file_ext_none">none</span>
<?php                   } else { ?>
                            <span class="file_ext_map"><?= implode(', ', array_map(function ($x) use ($extMap) { return "$x:" . $extMap[$x]; }, array_keys($extMap))) ?></span>
<?php                   } ?>
                        </span>
<?php                   if (strlen($ExtraDescription)) { ?>
                        <br /><span class="report_other_torrent_info" title="Release description of other torrent">Release info: <?= Text::full_format($ExtraDescription) ?></span>
<?php                   } ?>
                    </td>
                </tr>
<?php                   if ($HasLog || $ExtraHasLog) { ?>
                <tr>
                    <td class="label">Logfiles:</td>
                    <td colspan="3">
                        <table><tr><td>Reported</td><td>Relevant</td></tr><tr>
                            <td width="50%" style="vertical-align: top; max-width: 500px;">
<?php                       $log = new Gazelle\Torrent\Log($TorrentID);
                            $details = $log->logDetails(); ?>
                                <ul class="nobullet logdetails">
<?php                       if (!count($details)) { ?>
                                <li class="nobr">No logs</li>
<?php
                            } else {
                                foreach ($details as $logId => $info) {
                                    if ($info['adjustment']) {
                                        $adj = $info['adjustment'];
?>
                                <li class="nobr">Log adjusted by <?= Users::format_username($adj['userId'])
                                    ?> from score <?= $adj['score']
                                    ?> to <?= $adj['adjusted'] . ($adj['reason'] ? ', reason: ' .  $adj['reason'] : '') ?></li>
<?php
                                    }
                                    if (isset($info['status']['tracks'])) {
                                        $info['status']['tracks'] = implode(', ', array_keys($info['status']['tracks']));
                                    }
                                    foreach ($info['status'] as $s) {
?>
                                <li class="nobr"><?= $s ?></li>
<?php                               } ?>
                                <li><span class="nobr"><strong>Raw logfile #<?= $logId ?></strong>:
                                    </span><a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a><pre class="hidden"><?=
                                        $ripFiler->get([$TorrentID, $logId]) ?></pre></li>
                                <li><span class="nobr"><strong>HTML logfile #<?= $logId ?></strong>:
                                    </span><a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a><pre class="hidden"><?= $info['log'] ?></pre></li>
<?php
                                }
                            }
?>
                                </ul>
                            </td>
                            <td width="50%" style="vertical-align: top; max-width: 500px;">
<?php
                            $log = new Gazelle\Torrent\Log($ExtraID);
                            $details = $log->logDetails();
?>
                                <ul class="nobullet logdetails">
<?php                       if (!count($details)) { ?>
                                <li class="nobr">No logs</li>
<?php
                            } else {
                                foreach ($details as $logId => $info) {
                                    if ($info['adjustment']) {
                                        $adj = $info['adjustment']; ?>
                                <li class="nobr">Log adjusted by <?= Users::format_username($adj['userId'])
                                    ?> from score <?= $adj['score']
                                    ?> to <?= $adj['adjusted'] . ($adj['reason'] ? ', reason: ' .  $adj['reason'] : '') ?></li>
<?php
                                    }
                                    if (isset($info['status']['tracks'])) {
                                        $info['status']['tracks'] = implode(', ', array_keys($info['status']['tracks']));
                                    }
                                    foreach ($info['status'] as $s) {
?>
                                <li class="nobr"><?= $s ?></li>
<?php                               } ?>
                                <li><span class="nobr"><strong>Raw logfile #<?= $logId ?></strong>:
                                    </span><a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a><pre class="hidden"><?=
                                        $ripFiler->get([$ExtraID, $logId]) ?></pre></li>
                                <li><span class="nobr"><strong>HTML logfile #<?= $logId ?></strong>:
                                    </span><a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a><pre class="hidden"><?= $info['log'] ?></pre></li>
<?php
                                }
                            }
?>
                                </ul>
                            </td>
                        </tr></table>
                    </td>
                </tr>
<?php                   } ?>
                <tr>
                    <td class="label">Switch:</td>
                    <td colspan="3"><a href="#" onclick="Switch(<?=$ReportID?>, <?=$TorrentID?>, <?=$ExtraID?>); return false;" class="brackets">Switch</a> the source and target torrents (you become the report owner).
<?php
                    }
                }
?>
                    </td>
                </tr>
<?php
            }
            if ($Images) {
?>
                <tr>
                    <td class="label">Relevant images:</td>
                    <td colspan="3">
<?php
                $Images = explode(' ', $Images);
                foreach ($Images as $Image) {
?>
                        <img style="max-width: 200px;" onclick="lightbox.init(this, 200);" src="<?=ImageTools::process($Image)?>" alt="Relevant image" />
<?php           } ?>
                    </td>
                </tr>
<?php       } ?>
                <tr>
                    <td class="label">User comment:</td>
                    <td colspan="3" class="wrap_overflow"><?=Text::full_format($UserComment)?></td>
                </tr>
<?php       if ($Status == 'InProgress') { /* BEGIN MOD STUFF */ ?>
                <tr>
                    <td class="label">In progress by:</td>
                    <td colspan="3">
                        <a href="user.php?id=<?=$ResolverID?>"><?= $resolverName ?></a>
                    </td>
                </tr>
<?php
            }
            if ($Status != 'Resolved') {
?>
                <tr>
                    <td class="label">Report comment:</td>
                    <td colspan="3">
                        <input type="text" name="comment" id="comment<?=$ReportID?>" size="70" value="<?=$ModComment?>" />
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
<?php           } ?>
                        </select>
                        | <span id="options<?=$ReportID?>">
                            <span class="tooltip" title="Warning length in weeks">
                                <label for="warning<?=$ReportID?>"><strong>Warning</strong></label>
                                <select name="warning" id="warning<?=$ReportID?>">
<?php           foreach (range(0, 8) as $week) { ?>
                                    <option value="<?= $week ?>"><?= $week ?></option>
<?php           } ?>
                                </select>
                            </span> |
<?php           if (check_perms('users_mod')) { ?>
                            <span class="tooltip" title="Delete torrent?">
                                <input type="checkbox" name="delete" id="delete<?=$ReportID?>" />&nbsp;<label for="delete<?=$ReportID?>"><strong>Delete</strong></label>
                            </span> |
<?php           } ?>
                            <span class="tooltip" title="Remove upload privileges?">
                                <input type="checkbox" name="upload" id="upload<?=$ReportID?>" />&nbsp;<label for="upload<?=$ReportID?>"><strong>Remove upload privileges</strong></label>
                            </span> |
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
                            <option value="Reporter" selected="selected">Reporter</option>
                        </select>:
                    </td>
                    <td colspan="3">
                        <textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="50" rows="2"></textarea>
                        <input type="button" value="Send now" onclick="SendPM(<?=$ReportID?>);" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Extra</strong> log message:</td>
                    <td>
<?php
                if ($ExtraIDs) {
                    $Extras = explode(' ', $ExtraIDs);
                        $Value = '';
                        foreach ($Extras as $ExtraID) {
                            $Value .= SITE_URL . "/torrents.php?torrentid=$ExtraID ";
                        }
                } elseif (isset($ReportType['extra_log'])) {
                    $Value = $ReportType['extra_log'];
                }
?>
                        <input type="text" name="log_message" id="log_message<?=$ReportID?>" size="40" value="<?= trim($Value) ?>" />
                    </td>
                    <td class="label" title="These notes will be added to the user profile"><strong>Extra</strong> staff notes:</td>
                    <td>
                        <input type="text" name="admin_message" id="admin_message<?=$ReportID?>" size="40" />
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: center;">
                        <input type="button" value="Invalidate report" onclick="Dismiss(<?=$ReportID?>);" />
                        | <input type="button" value="Resolve report manually" onclick="ManualResolve(<?=$ReportID?>);" />
<?php           if ($Status == 'InProgress' && $LoggedUser['ID'] == $ResolverID) { ?>
                        | <input type="button" value="Unclaim" onclick="GiveBack(<?=$ReportID?>);" />
<?php           } else { ?>
                        | <input id="grab<?=$ReportID?>" type="button" value="Claim" onclick="Grab(<?=$ReportID?>);" />
<?php           } ?>
                        | <span class="tooltip" title="All checked reports will be resolved via the Multi-resolve button">
                            <input type="checkbox" name="multi" id="multi<?=$ReportID?>" />&nbsp;<label for="multi">Multi-resolve</label>
                          </span>
                        | <input type="button" id="submit_<?=$ReportID?>" value="Submit" onclick="TakeResolve(<?=$ReportID?>);" />
                    </td>
                </tr>
<?php       } else { ?>
                <tr>
                    <td class="label">Resolver:</td>
                    <td colspan="3">
                        <a href="user.php?id=<?=$ResolverID?>"><?= $resolverName ?></a>
                    </td>
                </tr>
                <tr>
                    <td class="label">Resolve time:</td>
                    <td colspan="3">
                        <?=time_diff($LastChangeTime); echo "\n"; ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Report comments:</td>
                    <td colspan="3">
                        <?=$ModComment; echo "\n"; ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Log message:</td>
                    <td colspan="3">
                        <?=$LogMessage; echo "\n"; ?>
                    </td>
                </tr>
<?php           if ($GroupID) { ?>
                <tr>
                    <td    colspan="4" style="text-align: center;">
                        <input id="grab<?=$ReportID?>" type="button" value="Claim" onclick="Grab(<?=$ReportID?>);" />
                    </td>
                </tr>
<?php
                }
            }
?>
            </table>
        </form>
    </div>
    <script type="text/javascript">//<![CDATA[
        Load(<?=$ReportID?>);
    //]]>
    </script>
<?php
        }
    }
}
?>
</div>
<?php
echo $paginator->linkbox();
View::show_footer();
