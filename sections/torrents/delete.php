<?php

use Gazelle\Util\Time;

$torMan        = new Gazelle\Manager\Torrent();
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$reportTypeMan = new Gazelle\Manager\Torrent\ReportType();
$userMan       = new Gazelle\Manager\User();

$torrent = $torMan->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('This torrent has already been deleted.');
}
$torrentId  = $torrent->id();
$uploaderId = $torrent->uploaderId();

if ($torrent->hasUploadLock()) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

if ($Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !$Viewer->permitted('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}

if ($Viewer->id() != $uploaderId && !$Viewer->permitted('torrents_delete')) {
    error(403);
}

if (Time::timeAgo($torrent->created()) > 3600 * 24 * 7 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been uploaded for over a week. If you now think there is a problem, please report the torrent instead.');
}

if ($torrent->snatchTotal() >= 5 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been snatched by 5 or more users. If you believe there is a problem with this torrent, please report it instead.');
}

$size = '(' . number_format($torrent->size() / (1024 * 1024), 2) . ' MiB)';

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
                <input type="hidden" name="torrentid" value="<?= $torrentId ?>" />
                <div class="field_div">
                    <strong>Reason: </strong>
                    <select name="reason">
                        <option value="dupe">Dupe</option>
                        <option value="trump">Trumped</option>
                        <option value="other" selected="selected">Other</option>
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
<?php
if ($Viewer->permitted('admin_reports')) {
    $reportType = $reportTypeMan->findByType('dupe');
?>
<div id="all_reports" style="width: 80%; margin-left: auto; margin-right: auto;">
<div id="report0" class="report">
    <form class="create_form" name="report" id="reportform_0" action="reports.php" method="post">
<?php /* Some of these are for takeresolve, some for the JavaScript. */ ?>
        <div>
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" id="reportid0" name="reportid" value="0" />
            <input type="hidden" id="torrentid0" name="torrentid" value="<?= $torrentId ?>" />
            <input type="hidden" id="uploader0" name="uploader" value="<?= $torrent->uploader()->username() ?>" />
            <input type="hidden" id="uploaderid0" name="uploaderid" value="<?= $uploaderId ?>" />
            <input type="hidden" id="reporterid0" name="reporterid" value="<?= $Viewer->id() ?>" />
            <input type="hidden" id="raw_name0" name="raw_name" value="<?= html_escape($torrent->fullName()) . " $size" ?>" />
            <input type="hidden" id="type0" name="type" value="<?= $reportType->type() ?>" />
            <input type="hidden" id="categoryid0" name="categoryid" value="<?= $torrent->group()->categoryId() ?>" />
            <input type="hidden" id="pm_type0" name="pm_type" value="Uploader" />
            <input type="hidden" id="from_delete0" name="from_delete" value="<?= $torrent->groupId() ?>" />
        </div>
        <table cellpadding="5" class="box layout">
            <tr>
                <td class="label">Torrent:</td>
                <td colspan="3">
                    <?= $torrent->fullLink() ?> <?= $size ?>
                    <a href="torrents.php?action=download&amp;id=<?= $torrentId ?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" class="brackets tooltip" title="Download">DL</a>
                    uploaded by <?= $torrent->uploader()->link() ?> <?= time_diff($torrent->created()) ?>
                    <br />
<?php
    $GroupOthers = $torrent->group()->unresolvedReportsTotal();
    if ($GroupOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=group&amp;id=<?= $torrent->groupId() ?>">There <?= $GroupOthers > 1 ? "are $GroupOthers reports" : "is 1 other report" ?> for torrent(s) in this group</a>
                        </div>
<?php
    }
    $UploaderOthers = $torrent->uploader()->stats()->unresolvedReportsTotal();
    if ($UploaderOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="reportsv2.php?view=uploader&amp;id=<?= $torrent->uploaderId() ?>">There <?= $UploaderOthers > 1 ? "are $UploaderOthers reports" : "is 1 other report" ?> for torrent(s) uploaded by this user</a>
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
?>
                    </td>
                </tr>
<?php /* END REPORTED STUFF - BEGIN MOD STUFF */ ?>
                <tr>
                    <td class="label">
                        <a href="javascript:Load('0')" class="tooltip" title="Click here to reset the resolution options to their default values.">Resolve:</a>
                    </td>
                    <td colspan="3">
                        <select name="resolve_type" id="resolve_type0" onchange="ChangeResolve(0);">
<?php foreach ($reportTypeMan->categoryList($torrent->group()->categoryId()) as $rt) { ?>
                            <option value="<?= $rt->type() ?>"<?= $rt->type() === $reportType->type() ? ' selected' : '' ?>><?= $rt->name() ?></option>
<?php } ?>
                        </select>
                        <span id="options0">
                            <span class="tooltip" title="Delete torrent?">
                                <label for="delete0"><strong>Delete</strong></label>
                                <input type="checkbox" name="delete" id="delete0"<?= $reportType->doDeleteUpload() ? ' checked' : '' ?> />
                            </span>
                            <span class="tooltip" title="Warning length in weeks">
                                <label for="warning0"><strong>Warning</strong></label>
                                <select name="warning" id="warning0">
<?php foreach (range(0, 8) as $week) { ?>
                                    <option value="<?= $week ?>"<?= $reportType->warnWeeks() === $week ? ' selected' : '' ?>><?= $week ?></option>
<?php } ?>
                                </select>
                            </span>
                            <span class="tooltip" title="Remove upload privileges?">
                                <label for="upload0"><strong>Remove upload privileges</strong></label>
                                <input type="checkbox" name="upload" id="upload0"<?= $reportType->doRevokeUploadPrivs() ? ' checked' : '' ?> />
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="label">PM uploader:</td>
                    <td colspan="3">
                        <span class="tooltip" title="Appended to the regular message unless using &quot;Send now&quot;.">
                            <textarea name="uploader_pm" id="uploader_pm0" cols="50" rows="1"></textarea>
                        </span>
                        <input type="button" value="Send now" onclick="SendPM(0);" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Extra</strong> log message:</td>
                    <td>
                        <input type="text" name="log_message" id="log_message0" size="40" />
                    </td>
                    <td class="label"><strong>Extra</strong> staff notes:</td>
                    <td>
                        <input type="text" name="admin_message" id="admin_message0" size="40" />
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: center;">
                        <input type="button" value="Submit" onclick="TakeResolve(0);" />
                    </td>
                </tr>
            </table>
        </form>
        <br />
    </div>
</div>
<?php
}
View::show_footer();
