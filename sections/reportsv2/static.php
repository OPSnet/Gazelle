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

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$torMan        = new Gazelle\Manager\Torrent;
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$reportTypeMan = new Gazelle\Manager\Torrent\ReportType;
$userMan       = new Gazelle\Manager\User;
$search        = new Gazelle\Search\Torrent\Report($_GET['view'] ?? '', $_GET['id'] ?? '', $reportTypeMan, $userMan);
$imgProxy      = new Gazelle\Util\ImageProxy($Viewer);
$ripFiler      = new Gazelle\File\RipLog;
$htmlFiler     = new Gazelle\File\RipLogHTML;

$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$page = $search->page($reportMan, $paginator->limit(), $paginator->offset());

View::show_header('Torrent Reports', ['js' => 'reportsv2,bbcode,browse,torrent']);
?>
<div class="header">
    <h2><?= $search->title() ?></h2>
<?php require_once('header.php'); ?>
</div>
<div class="buttonbox pad center">
<?php if ($search->mode() !== 'resolved') { ?>
    <span class="tooltip" title="Resolves *all* checked reports with their respective resolutions"><input type="button" onclick="MultiResolve();" value="Multi-resolve" /></span>
    <span class="tooltip" title="Assigns all of the reports on the page to you!"><input type="button" onclick="Grab();" value="Claim all" /></span>
<?php
}
if ($search->canUnclaim($Viewer)) {
?>
    | <span class="tooltip" title="Unclaim all of the reports currently displayed"><input type="button" onclick="GiveBack();" value="Unclaim all" /></span>
<?php } ?>
</div>
<?= $paginator->linkbox() ?>
<div id="all_reports">
<?php if (count($page) === 0) { ?>
    <div class="box pad center">
        <strong>No reports here! \o/</strong>
    </div>
<?php
} else {
    foreach ($page as $report) {
        $reportId   = $report->id();
        $reporterId = $report->reporterId();
        $resolverId = $report->resolverId();
        $reporterName = $userMan->findById($reporterId)?->username() ?? 'System';
        $resolverName = $userMan->findById($resolverId)?->username() ?? 'System';

        if (is_null($report->torrent()) && $report->status() != 'Resolved') {
            //Torrent already deleted
            $report->resolve('Report already dealt with (torrent deleted)');
?>
    <div id="report<?= $reportId ?>" class="report box pad center">
        <a href="reportsv2.php?view=report&amp;id=<?= $reportId ?>">Report <?= $reportId ?></a> for torrent <?= $report->torrentId()
        ?> (deleted) has been automatically resolved. <input type="button" value="Hide" onclick="ClearReport(<?= $reportId ?>);" />
    </div>
<?php
        } else {
            $reportType   = $report->reportType();
            $torrent      = $report->torrent();
            $torrentId    = $report->torrentId();

            $tgroupId     = $torrent->groupId();
            $categoryId   = (int)((new Gazelle\TGroup($tgroupId))?->categoryId());
            $size         = '(' . number_format($torrent->size() / (1024 * 1024), 2) . ' MiB)';
            $link         = $torrent?->fullEditionLink() ?? 'deleted torrent';
            $RawName      = ($torrent?->fullName() ?? 'delete torrent') . " $size";
            $uploaderId   = $torrent->uploaderId();
            $uploaderName = $userMan->findById((int)$uploaderId)?->username() ?? 'System';
?>
    <div id="report<?= $reportId ?>">
        <form class="manage_form" style="80%" name="report" id="reportform_<?= $reportId ?>" action="reports.php" method="post">
<?php
/*
* Some of these are for takeresolve, namely the ones that aren't inputs, some for the JavaScript.
*/
?>
            <div>
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <input type="hidden" id="reportid<?= $reportId ?>" name="reportid" value="<?= $reportId ?>" />
                <input type="hidden" id="torrentid<?= $reportId ?>" name="torrentid" value="<?= $torrentId ?>" />
                <input type="hidden" id="uploader<?= $reportId ?>" name="uploader" value="<?= $uploaderName ?>" />
                <input type="hidden" id="uploaderid<?= $reportId ?>" name="uploaderid" value="<?= $uploaderId ?>" />
                <input type="hidden" id="reporterid<?= $reportId ?>" name="reporterid" value="<?= $reporterId ?>" />
                <input type="hidden" id="report_reason<?= $reportId ?>" name="report_reason" value="<?= $report->reason() ?>" />
                <input type="hidden" id="raw_name<?= $reportId ?>" name="raw_name" value="<?=$RawName?>" />
                <input type="hidden" id="type<?= $reportId ?>" name="type" value="<?= $reportType->type() ?>" />
                <input type="hidden" id="categoryid<?= $reportId ?>" name="categoryid" value="<?= $report->reportType()->categoryId() ?>" />
            </div>
            <table class="box layout" cellpadding="5">
                <tr>
                    <td class="label"><a href="reportsv2.php?view=report&amp;id=<?= $reportId ?>">Reported</a> torrent:</td>
                    <td>
                        <?= $link ?> <?= $size ?>
                        <br /><a href="torrents.php?action=download&amp;id=<?= $torrentId ?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
                        <a href="#" class="brackets tooltip" onclick="show_downloads('<?=( $torrentId )?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">Downloaders</a>
                        <a href="#" class="brackets tooltip" onclick="show_snatches('<?=( $torrentId )?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">Snatchers</a>
                        <a href="#" class="brackets" onclick="show_seeders('<?=( $torrentId )?>', 0); return false;">Seeders</a>
                        <a href="#" class="brackets" onclick="show_files('<?=( $torrentId )?>'); return false;">Contents</a>
                        <div id="viewlog_<?= $torrentId ?>" class="hidden"></div>
                        <div id="peers_<?= $torrentId ?>" class="hidden"></div>
                        <div id="downloads_<?= $torrentId ?>" class="hidden"></div>
                        <div id="snatches_<?= $torrentId ?>" class="hidden"></div>
                        <div id="files_<?= $torrentId ?>" class="hidden">
                            <table class="filelist_table">
                                <tr class="colhead_dark">
                                    <td>
                                        <div class="filelist_title" style="float: left;">File Names</div>
                                        <div class="filelist_path" style="float: right;"><?= $torrent?->path() ? "/{$torrent?->path()}/" : '.' ?></div>
                                    </td>
                                    <td class="nobr" style="text-align: right">
                                        <strong>Size</strong>
                                    </td>
                                </tr>
<?php
            foreach ($torrent?->fileList() ?? [] as $f) {
                $info = $torMan->splitMetaFilename($f);
?>
                                <tr><td><?= $info['name'] ?></td><td class="number_column nobr"><?= Format::get_size($info['size']) ?></td></tr>
<?php       } ?>
                            </table>
                        </div>
                        <br /><span class="report_reporter">reported by <a href="user.php?id=<?= $report->reporterId() ?>"><?= $reporterName ?></a> <?=time_diff($report->created())?> for the reason: <strong><?= $report->reportType()->name() ?></strong></span>
                        <br />uploaded by <a href="user.php?id=<?= $uploaderId ?>"><?= $uploaderName  ?></a> on <span title="<?= time_diff($torrent?->uploadDate(), 3, false) ?>"><?= $torrent?->uploadDate() ?></span>
                        <br />Last action: <?= $torrent?->lastActiveDate() ?: 'Never' ?>
                        <br /><span class="report_torrent_file_ext">Audio files present:
<?php
            $extMap = $torMan->audioMap($torrent?->fileList() ?? []);
            if (count($extMap) == 0) {
?>
                            <span class="file_ext_none">none</span>
<?php       } else { ?>
                            <span class="file_ext_map"><?= implode(', ', array_map(fn ($ext) => "$ext: {$extMap[$ext]}", array_keys($extMap))) ?></span>
<?php       } ?>
                        </span>
<?php       if ($torrent?->description()) { ?>
                        <br /><div class="report_torrent_info" title="Release description of reported torrent">Release info: <?= Text::full_format($torrent?->description()) ?></div>
<?php       } ?>

<?php       if ($report->status() != 'Resolved') {
                $totalGroup = $reportMan->totalReportsGroup($tgroupId);
                if ($totalGroup > 1) {
                    --$totalGroup;
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=group&amp;id=<?= $tgroupId ?>">There <?=
                                $totalGroup > 1 ? "are $totalGroup other reports" : "is 1 other report"
                                ?> for torrent(s) in this group</a>
                        </div>
<?php
                }
                $totalUploaded = $reportMan->totalReportsUploader($uploaderId);
                if ($totalUploaded > 1) {
                    --$totalUploaded;
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=uploader&amp;id=<?= $uploaderId ?>">There <?=
                                $totalUploaded > 1 ? "are $totalUploaded other reports" : "is 1 other report"
                                ?> for torrent(s) uploaded by this user</a>
                        </div>
<?php           }

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
                    ",  $torrentId
                );
                if ($DB->has_results()) {
                    while ([$RequestID, $FillerID, $FillerName, $FilledTime] = $DB->next_record()) {
?>
                        <div style="text-align: right;">
                            <strong class="important_text"><a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=view&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?></strong>
                        </div>
<?php
                    }
                }
            }
?>
                    </td>
                </tr>
<?php       if ($report->trackList()) { ?>
                <tr>
                    <td class="label">Relevant tracks:</td>
                    <td>
                        <?= implode(' ', $report->trackList()) ?>
                    </td>
                </tr>
<?php
            }

            if ($report->externalLink()) { ?>
                <tr>
                    <td class="label">Relevant links:</td>
                    <td>
<?php
                foreach ($report->externalLink() as $link) {

                    if ($local = Text::local_url($link)) {
                        $link = $local;
                    }
?>
                        <a href="<?= $link ?>"><?= $link ?></a>
<?php           } ?>
                    </td>
                </tr>
<?php
            }

            if ($report->otherIdList()) {
?>
                <tr>
                    <td class="label">Relevant other torrents:</td>
                    <td>
<?php
                $n = 0;
                foreach ($report->otherIdList() as $extraId) {
                    $extra = $torMan->findById($extraId)?->setViewer($Viewer);
                    if ($extra) {
?>
                        <?= $n++ == 0 ? '' : '<br />' ?>
                        <?= $extra->fullEditionLink() ?> (<?= number_format($extra->size() / (1024 * 1024), 2) ?> MiB)
                        <br /><a href="torrents.php?action=download&amp;id=<?= $extraId ?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
                        <a href="#" class="brackets tooltip" onclick="show_downloads('<?= $extraId ?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">Downloaders</a>
                        <a href="#" class="brackets tooltip" onclick="show_snatches('<?= $extraId ?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">Snatchers</a>
                        <a href="#" class="brackets" onclick="show_seeders('<?= $extraId ?>', 0); return false;">Seeders</a>
                        <a href="#" class="brackets" onclick="show_files('<?= $extraId ?>'); return false;">Contents</a>
                        <div id="viewlog_<?= $extraId ?>" class="hidden"></div>
                        <div id="peers_<?= $extraId ?>" class="hidden"></div>
                        <div id="downloads_<?= $extraId ?>" class="hidden"></div>
                        <div id="snatches_<?= $extraId ?>" class="hidden"></div>
                        <div id="files_<?= $extraId ?>" class="hidden">
                            <table class="filelist_table">
                                <tr class="colhead_dark">
                                    <td>
                                        <div class="filelist_title" style="float: left;">File Names</div>
                                        <div class="filelist_path" style="float: right;"><?= $extra->path() ? "/{$extra->path()}/" : '.' ?></div>
                                </td>
                                <td class="nobr" style="text-align: right">
                                    <strong>Size</strong>
                                </td>
                            </tr>
<?php
                        foreach ($extra->fileList() as $f) {
                            $info = $torMan->splitMetaFilename($f);
?>
                            <tr><td><?= $info['name'] ?></td><td class="number_column nobr"><?= Format::get_size($info['size']) ?></td></tr>
<?php                   } ?>
                        </table>
                        </div>
                        <br />uploaded by <a href="user.php?id=<?=$extra->uploaderId() ?>"><?=$extra->uploader()?->username() ?? 'System' ?></a> on <span title="<?=
                            time_diff($extra->uploadDate(), 3, false) ?>"><?= $extra->uploadDate() ?> (<?=
                            strtotime($extra->uploadDate()) < strtotime($torrent?->uploadDate() ?? '2000-01-01 00:00:00') ? 'older upload' : 'more recent upload' ?>)</span>
                        <br />Last action: <?= $extra->lastActiveDate() ?: 'Never' ?>
                        <br /><span>Audio files present:
<?php
                        $extMap = $torMan->audioMap($extra->fileList());
                        if (count($extMap) == 0) {
?>
                            <span class="file_ext_none">none</span>
<?php                   } else { ?>
                            <span class="file_ext_map"><?= implode(', ', array_map(fn ($ext) => "$ext: {$extMap[$ext]}", array_keys($extMap))) ?></span>
<?php                   } ?>
                        </span>
<?php                   if ($extra->description()) { ?>
                        <br /><span class="report_other_torrent_info" title="Release description of other torrent">Release info: <?= Text::full_format($extra->description()) ?></span>
<?php                   } ?>
                    </td>
                </tr>
<?php                   if ($torrent?->hasLog() || $extra->hasLog()) { ?>
                <tr>
                    <td class="label">Logfiles:</td>
                    <td>
                        <table><tr><td>Reported</td><td>Relevant</td></tr><tr>
                            <td width="50%" style="vertical-align: top; max-width: 500px;">
<?php
                            $log = new Gazelle\Torrent\Log( $torrentId );
                            $details = $log->logDetails();
?>
                                <ul class="nobullet logdetails">
<?php                       if (!count($details)) { ?>
                                <li class="nobr">No logs</li>
<?php
                            } else {
                                foreach ($details as $logId => $info) {
                                    if ($info['adjustment']) {
                                        $adj = $info['adjustment'];
                                        $adjUser = $userMan->findById($adj['userId']);
?>
                                <li>Log adjusted <?= $adjUser ? "by {$adjUser->link()}" : '' ?> from score <?= $adj['score']
                                    ?> to <?= $adj['adjusted'] . ($adj['reason'] ? ', reason: ' .  $adj['reason'] : '') ?></li>
<?php
                                    }
                                    if (isset($info['status']['tracks'])) {
                                        $info['status']['tracks'] = implode(', ', array_keys($info['status']['tracks']));
                                    }
                                    foreach ($info['status'] as $s) {
                                        if ($s) {
?>
                                <li><?= $s ?></li>
<?php
                                        }
                                    }
?>
                                <li>
                                    <span class="nobr"><strong>Logfile #<?= $logId ?></strong>: </span>
                                    <a href="javascript:void(0);" onclick="BBCode.spoiler(this);" class="brackets">Show</a><pre class="hidden"><?= $ripFiler->get([ $torrentId , $logId]) ?></pre>
                                </li>
                                <li>
                                    <span class="nobr"><strong>HTML logfile #<?= $logId ?></strong>: </span>
                                    <a href="javascript:void(0);" onclick="BBCode.spoiler(this);" class="brackets">Show</a><pre class="hidden"><?= $info['log'] ?></pre>
                                </li>
<?php
                                }
                            }
?>
                                </ul>
                            </td>
                            <td width="50%" style="vertical-align: top; max-width: 500px;">
<?php
                            $log = new Gazelle\Torrent\Log($extraId);
                            $details = $log->logDetails();
?>
                                <ul class="nobullet logdetails">
<?php                       if (!count($details)) { ?>
                                <li class="nobr">No logs</li>
<?php
                            } else {
                                foreach ($details as $logId => $info) {
                                    if ($info['adjustment']) {
                                        $adj = $info['adjustment'];
                                        $adjUser = $userMan->findById($adj['userId']);
?>
                                <li>Log adjusted <?= $adjUser ? "by {$adjUser->link()}" : '' ?> from score <?= $adj['score']
                                    ?> to <?= $adj['adjusted'] . ($adj['reason'] ? ', reason: ' .  $adj['reason'] : '') ?></li>
<?php
                                    }
                                    if (isset($info['status']['tracks'])) {
                                        $info['status']['tracks'] = implode(', ', array_keys($info['status']['tracks']));
                                    }
                                    foreach ($info['status'] as $s) {
?>
                                <li><?= $s ?></li>
<?php                               } ?>
                                <li>
                                    <span class="nobr"><strong>Raw logfile #<?= $logId ?></strong>: </span>
                                    <a href="javascript:void(0);" onclick="BBCode.spoiler(this);" class="brackets">Show</a><pre class="hidden"><?= $ripFiler->get([$extraId, $logId]) ?></pre>
                                </li>
                                <li>
                                    <span class="nobr"><strong>HTML logfile #<?= $logId ?></strong>: </span>
                                    <a href="javascript:void(0);" onclick="BBCode.spoiler(this);" class="brackets">Show</a><pre class="hidden"><?= $info['log'] ?></pre>
                                </li>
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
                    <td><a href="#" onclick="Switch(<?= $reportId ?>, <?= $extraId ?>); return false;" class="brackets">Switch</a> the source and target torrents (you become the report owner).
<?php
                    }
                }
?>
                    </td>
                </tr>
<?php
            }
            if ($report->image()) {
?>
                <tr>
                    <td class="label">Relevant images:</td>
                    <td>
<?php
                foreach ($report->image() as $image) {
?>
                        <img style="max-width: 200px;" onclick="lightbox.init(this, 200);" src="<?=
                            $imgProxy->process($image) ?>" alt="Relevant image" />
<?php           } ?>
                    </td>
                </tr>
<?php       } ?>
                <tr>
                    <td class="label">User comment:</td>
                    <td class="wrap_overflow"><?= Text::full_format($report->reason()) ?></td>
                </tr>
<?php       if ($report->status() == 'InProgress') { /* BEGIN MOD STUFF */ ?>
                <tr>
                    <td class="label">In progress by:</td>
                    <td>
                        <a href="user.php?id=<?= $resolverId ?>"><?= $resolverName ?></a>
                    </td>
                </tr>
<?php
            }
            if ($report->status() != 'Resolved') {
?>
                <tr>
                    <td class="label">Report comment:</td>
                    <td>
                        <input type="text" name="comment" id="comment<?= $reportId ?>" size="70" value="<?= display_str($report->comment()) ?>" />
                        <input type="button" value="Update now" onclick="UpdateComment(<?= $reportId ?>);" />
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <a href="javascript:Load('<?= $reportId ?>')" class="tooltip" title="Click here to reset the resolution options to their default values.">Resolve</a>:
                    </td>
                    <td>
                        <select name="resolve_type" id="resolve_type<?= $reportId ?>" onchange="ChangeResolve(<?= $reportId ?>);">
<?php           foreach ($reportTypeMan->categoryList($categoryId) as $rt) { ?>
                            <option value="<?= $rt->type() ?>"><?= $rt->name() ?></option>
<?php           } ?>
                        </select>
                        | <span id="options<?= $reportId ?>">
                            <span class="tooltip" title="Warning length in weeks">
                                <label for="warning<?= $reportId ?>"><strong>Warning</strong></label>
                                <select name="warning" id="warning<?= $reportId ?>">
<?php           foreach (range(0, 8) as $week) { ?>
                                    <option value="<?= $week ?>"><?= $week ?></option>
<?php           } ?>
                                </select>
                            </span> |
<?php           if ($Viewer->permitted('users_mod')) { ?>
                            <span class="tooltip" title="Delete torrent?">
                                <input type="checkbox" name="delete" id="delete<?= $reportId ?>" />&nbsp;<label for="delete<?= $reportId ?>"><strong>Delete</strong></label>
                            </span> |
<?php           } ?>
                            <span class="tooltip" title="Remove upload privileges?">
                                <input type="checkbox" name="upload" id="upload<?= $reportId ?>" />&nbsp;<label for="upload<?= $reportId ?>"><strong>Remove upload privileges</strong></label>
                            </span> |
                            <span class="tooltip" title="Update resolve type">
                                <input type="button" name="update_resolve" id="update_resolve<?= $reportId ?>" value="Update now" onclick="UpdateResolve(<?= $reportId ?>);" />
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="label tooltip" title="Uploader: Appended to the regular message unless using &quot;Send now&quot;. Reporter: Must be used with &quot;Send now&quot;.">
                        PM
                        <select name="pm_type" id="pm_type<?= $reportId ?>">
                            <option value="Uploader">Uploader</option>
                            <option value="Reporter" selected="selected">Reporter</option>
                        </select>:
                    </td>
                    <td>
                        <textarea name="uploader_pm" id="uploader_pm<?= $reportId ?>" cols="50" rows="2"></textarea>
                        <input type="button" value="Send now" onclick="SendPM(<?= $reportId ?>);" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Extra</strong> log message:</td>
                    <td>
                        <input type="text" name="log_message" id="log_message<?= $reportId ?>" size="40" value="<?= trim($report->message() ?? '') ?>" />
                    </td>
                <tr>
                    <td class="label"><strong>Extra</strong> staff notes:</span>
                    <td>
                        <input type="text" name="admin_message" id="admin_message<?= $reportId ?>" size="40" />
                        (These notes will be added to the user profile)
                    </td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td>
                        <input type="button" value="Invalidate report" onclick="Dismiss(<?= $reportId ?>);" />
                        | <input type="button" value="Resolve report manually" onclick="ManualResolve(<?= $reportId ?>);" />
<?php           if ($report->status() == 'InProgress' && $Viewer->id() == $resolverId) { ?>
                        | <input type="button" value="Unclaim" onclick="GiveBack(<?= $reportId ?>);" />
<?php           } else { ?>
                        | <input id="grab<?= $reportId ?>" type="button" value="Claim" onclick="Grab(<?= $reportId ?>);" />
<?php           } ?>
                        | <span class="tooltip" title="All checked reports will be resolved via the Multi-resolve button">
                            <input type="checkbox" name="multi" id="multi<?= $reportId ?>" />&nbsp;<label for="multi">Multi-resolve</label>
                          </span>
                        | <input type="button" id="submit_<?= $reportId ?>" value="Submit" onclick="TakeResolve(<?= $reportId ?>);" />
                    </td>
                </tr>
<?php       } else { ?>
                <tr>
                    <td class="label">Resolver:</td>
                    <td>
                        <a href="user.php?id=<?=$resolverId?>"><?= $resolverName ?></a>
                    </td>
                </tr>
                <tr>
                    <td class="label">Resolve time:</td>
                    <td>
                        <?= time_diff($report->modified()) ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Report comments:</td>
                    <td>
                        <?= display_str($report->comment()) ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Log message:</td>
                    <td>
                        <?= $report->message() ?? '' ?>
                    </td>
                </tr>
<?php           if ($torrent ) { ?>
                <tr>
                    <td>&nbsp;</td>
                    <td>
                        <input id="grab<?= $reportId ?>" type="button" value="Claim" onclick="Grab(<?= $reportId ?>);" />
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
        Load(<?= $reportId ?>);
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
