<?php
/*
 * This is the AJAX page that gets called from the JavaScript
 * function NewReport(), any changes here should probably be
 * replicated on static.php.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$imgProxy      = new Gazelle\Util\ImageProxy($Viewer);
$torMan        = new Gazelle\Manager\Torrent;
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$reportTypeMan = new Gazelle\Manager\Torrent\ReportType;
$reqMan        = new Gazelle\Manager\Request;
$userMan       = new Gazelle\Manager\User;

$report    = $reportMan->findNewest();
$reportId  = $report->id();
$torrentId = $report->torrentId();
if (is_null($report->torrent())) {
?>
<div id="report<?= $reportId ?>" class="report box pad center" data-reportid="<?= $reportId ?>">
<a href="reportsv2.php?view=report&amp;id=<?= $reportId ?>">Report <?= $reportId ?></a> for torrent <?= $torrentId ?> (deleted) has been automatically resolved. <input type="button" value="Clear" onclick="ClearReport(<?= $reportId ?>);" />
</div>
<?php
    $report->resolve('Report already dealt with (torrent deleted)');
    $torMan->findById($report->torrentId())?->flush();
    exit;
}
$report->claim($Viewer->id());

$torrent  = $report->torrent();
$tgroupId = $torrent->groupId();
$size     = '(' . number_format($torrent->size() / (1024 * 1024), 2) . ' MiB)';
$linkName = "{$torrent->fullLink()} $size";
$BBName   = "[pl]{$torrentId}[/pl] $size";
$RawName  = "{$torrent->fullName()} $size";
$uploader = $torrent->uploader();
$reporter = $userMan->findById($report->reporterId());

?>
<div id="report<?= $reportId ?>" class="report" data-reportid="<?= $reportId ?>">
    <form class="edit_form" name="report" id="reportform_<?= $reportId ?>" action="reports.php" method="post">
<?php /* Some of these are for takeresolve, some for the JavaScript. */ ?>
<div>
    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
    <input type="hidden" id="reportid<?= $reportId ?>" name="reportid" value="<?= $reportId ?>" />
    <input type="hidden" id="torrentid<?= $reportId ?>" name="torrentid" value="<?= $torrentId ?>" />
    <input type="hidden" id="uploader<?= $reportId ?>" name="uploader" value="<?= $uploader->username() ?>" />
    <input type="hidden" id="uploaderid<?= $reportId ?>" name="uploaderid" value="<?= $uploader->id() ?>" />
    <input type="hidden" id="reporterid<?= $reportId ?>" name="reporterid" value="<?= $reporter->id() ?>" />
    <input type="hidden" id="raw_name<?= $reportId ?>" name="raw_name" value="<?= html_escape($RawName) ?>" />
    <input type="hidden" id="type<?= $reportId ?>" name="type" value="<?= $report->reportType()->type() ?>" />
    <input type="hidden" id="categoryid<?= $reportId ?>" name="categoryid" value="<?= $report->reportType()->categoryId() ?>" />
</div>
<table class="box layout" cellpadding="5">
    <tr>
        <td class="label"><a href="<?= $report->url() ?>">Reported</a> torrent:</td>
        <td colspan="3">
            <?=$linkName?>
            <a href="torrents.php?action=download&amp;id=<?= $torrentId ?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
            uploaded by <?= $uploader->link() ?> <?=time_diff($torrent->created()) ?>
            <br />
            <div style="text-align: right;">was reported by <?= $reporter->link() ?> <?=time_diff($report->created())?> for the reason: <strong><?= $report->reportType()->name() ?></strong></div>
<?php
    $totalGroup = $reportMan->totalReportsGroup($tgroupId);
    if ($totalGroup > 1) {
        --$totalGroup;
?>
            <div style="text-align: right;">
                <a href="reportsv2.php?view=group&amp;id=<?=$tgroupId?>">There <?= $totalGroup > 1
                    ? "are $totalGroup other reports" : "is 1 other report" ?> for torrents in this group</a>
            </div>
<?php
    }
    $totalUploader = $reportMan->totalReportsUploader($uploader->id());
    if ($totalUploader > 1) {
        --$totalUploader;
?>
            <div style="text-align: right;">
                <a href="reportsv2.php?view=uploader&amp;id=<?=$uploader->id() ?>">There <?= $totalUploader > 1
                    ? "are $totalUploader other reports" : "is 1 other report" ?> for torrents uploaded by this user</a>
            </div>
<?php
    }
    foreach ($reqMan->findByTorrentReported($torrent) as $request) {
?>
                <div style="text-align: right;">
                    <strong class="important_text"><?= $userMan->findById($request->fillerId())->link() ?> used this torrent to fill <?= $request->link() ?> <?= time_diff($request->fillDate()) ?></strong>
                </div>
<?php } ?>
        </td>
    </tr>
<?php if ($report->trackList()) { ?>
    <tr>
        <td class="label">Relevant tracks:</td>
        <td colspan="3">
            <?= implode(' ', $report->trackList()) ?>
        </td>
    </tr>
<?php
}

if ($report->externalLink()) { ?>
    <tr>
        <td class="label">Relevant links:</td>
        <td colspan="3">
<?php
    foreach ($report->externalLink() as $link) {
        if ($local = Text::local_url($link)) {
            $link = $local;
        }
?>
            <a href="<?= $link ?>"><?= $link ?></a>
<?php } ?>
        </td>
    </tr>
<?php
}
if ($report->otherIdList()) {
?>
    <tr>
        <td class="label">Relevant other torrents:</td>
        <td colspan="3">
<?php
    $n = 0;
    foreach ($report->otherIdList() as $extraId) {
        $extra = $torMan->findById($extraId);
        if ($extra) {
?>
                <?= $n++ == 0 ? '' : '<br />' ?>
                <?= $extra->fullLink() ?> (<?= number_format($extra->size() / (1024 * 1024), 2) ?> MiB)
                <a href="torrents.php?action=download&amp;id=<?=$extra->id() ?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
                uploaded by <?= $extra->uploader()->link() ?> <?=time_diff($extra->created()) ?> <a href="#" onclick="Switch(<?= $reportId ?>, <?= $extra->id() ?>); return false;" class="brackets">Switch</a>
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
        <td colspan="3">
<?php
    foreach ($report->image() as $image) {
?>
            <img style="max-width: 200px;" onclick="lightbox.init(this, 200);" src="<?= html_escape(image_cache_encode($image)) ?>" alt="Relevant image" />
<?php } ?>
        </td>
    </tr>
<?php } ?>

    <tr>
        <td class="label">User comment:</td>
        <td colspan="3"><?=Text::full_format($report->reason())?></td>
    </tr>
<?php                    /* END REPORTED STUFF :|: BEGIN MOD STUFF */ ?>
    <tr>
        <td class="label">Report comment:</td>
        <td colspan="3">
            <input type="text" name="comment" id="comment<?= $reportId ?>" size="70" value="<?= html_escape($report->comment()) ?>" />
            <input type="button" value="Update now" onclick="UpdateComment(<?= $reportId ?>);" />
        </td>
    </tr>
    <tr>
        <td class="label">
            <a href="javascript:Load('<?= $reportId ?>')" class="tooltip" title="Click here to reset the resolution options to their default values.">Resolve</a>:
        </td>
        <td colspan="3">
            <select name="resolve_type" id="resolve_type<?= $reportId ?>" onchange="ChangeResolve(<?= $reportId ?>);">
<?php foreach ($reportTypeMan->categoryList($report->reportType()->categoryId()) as $rt) { ?>
                <option value="<?= $rt->type() ?>"><?= $rt->name() ?></option>
<?php } ?>
            </select>
            <span id="options<?= $reportId ?>">
<?php if ($Viewer->permitted('users_mod')) { ?>
                <span class="tooltip" title="Delete torrent?">
                    <label for="delete<?= $reportId ?>"><strong>Delete</strong></label>
                    <input type="checkbox" name="delete" id="delete<?= $reportId ?>" />
                </span>
<?php } ?>
                <span class="tooltip" title="Warning length in weeks">
                    <label for="warning<?= $reportId ?>"><strong>Warning</strong></label>
                    <select name="warning" id="warning<?= $reportId ?>">
<?php foreach (range(0, 8) as $week) { ?>
                        <option value="<?=$week?>"><?=$week?></option>
<?php } ?>
                    </select>
                </span>
                <span class="tooltip" title="Remove upload privileges?">
                    <label for="upload<?= $reportId ?>"><strong>Remove upload privileges</strong></label>
                    <input type="checkbox" name="upload" id="upload<?= $reportId ?>" />
                </span>
                &nbsp;&nbsp;
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
                <option value="Reporter">Reporter</option>
            </select>:
        </td>
        <td colspan="3">
            <textarea name="uploader_pm" id="uploader_pm<?= $reportId ?>" cols="50" rows="1"></textarea>
            <input type="button" value="Send now" onclick="SendPM(<?= $reportId ?>);" />
        </td>
    </tr>
    <tr>
        <td class="label"><strong>Extra</strong> log message:</td>
        <td>
            <input type="text" name="log_message" id="log_message<?= $reportId ?>" size="40"
<?php
if ($report->otherIdList()) {
    echo ' value="'
        . implode(' ', array_map(fn ($id) => "torrents.php?torrentid=$id", $report->otherIdList()))
        . '"';
}
?>
            />
        </td>
        <td class="label"><strong>Extra</strong> staff notes:</td>
        <td>
            <input type="text" name="admin_message" id="admin_message<?= $reportId ?>" size="40" />
        </td>
    </tr>
    <tr>
        <td colspan="4" style="text-align: center;">
            <input type="button" value="Invalidate report" onclick="Dismiss(<?= $reportId ?>);" />
            <input type="button" value="Resolve report manually" onclick="ManualResolve(<?= $reportId ?>);" />
            | <input type="button" value="Unclaim" onclick="GiveBack(<?= $reportId ?>);" />
            | <input id="grab<?= $reportId ?>" type="button" value="Claim" onclick="Grab(<?= $reportId ?>);" />
            | Multi-resolve <input type="checkbox" name="multi" id="multi<?= $reportId ?>" checked="checked" />
            | <input type="button" value="Submit" onclick="TakeResolve(<?= $reportId ?>);" />
        </td>
    </tr>
</table>
</form>
</div>
