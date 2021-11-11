<?php
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

$reportMan = new Gazelle\Manager\ReportV2;
$torMan = new Gazelle\Manager\Torrent;
$torMan->setViewer($Viewer);
$userMan = new Gazelle\Manager\User;
$Types = $reportMan->types();

$torrent = $torMan->findById((int)$_GET['id']);
if (is_null($torrent)) {
    // Deleted torrent
    header("Location: log.php?search=Torrent+" . $TorrentID);
    exit;
}
$tgroup = $torrent->group();

$CategoryID  = $tgroup->CategoryID();
$GroupID     = $tgroup->id();
$TorrentID   = $torrent->id();
$DisplayName = $tgroup->link() . " [{$tgroup->year()}] [{$tgroup->releaseTypeName()}]";
$AltName     = $torrent->fullName();

$urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';

if (empty($Types[$CategoryID])) {
    $TypeList = $Types['master'];
} else {
    $TypeList = $Types['master'] + $Types[$CategoryID];
    $Priorities = [];
    foreach ($TypeList as $Key => $Value) {
        $Priorities[$Key] = $Value['priority'];
    }
    array_multisort($Priorities, SORT_ASC, $TypeList);
}

View::show_header('Report', ['js' => 'reportsv2,browse,torrent,bbcode']);
?>

<div class="thin">
    <div class="header">
        <h2>Report a torrent</h2>
    </div>
    <div class="header">
        <h3><?=$DisplayName?></h3>
    </div>
    <div class="thin">
        <table class="torrent_table details<?= $torrent->isSnatched($Viewer->id()) ? ' snatched' : '' ?>" id="torrent_details">
            <tr class="colhead_dark">
                <td width="80%"><strong>Reported torrent</strong></td>
                <td><strong>Size</strong></td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php

$remasterTuple  = false;
$FirstUnknown   = $torrent->isRemasteredUnknown();
$Reports        = $torMan->reportList($Viewer, $TorrentID);
$NumReports     = count($Reports);
$Reported       = $NumReports > 0;
$EditionID      = 0;

if ($NumReports > 0) {
    require_once(__DIR__ . '/../reports/array.php');
    $ReportInfo = '
    <table class="reportinfo_table">
        <tr class="colhead_dark" style="font-weight: bold;">
            <td>This torrent has ' . $NumReports . ' active report' . plural($NumReports) . ":</td>
        </tr>";

    foreach ($Reports as $Report) {
        if ($Viewer->permitted('admin_reports')) {
            $ReporterID = $Report['ReporterID'];
            $ReporterName = $userMan->findById($ReporterID)->username();
            $ReportLinks = "<a href=\"user.php?id=$ReporterID\">$ReporterName</a> <a href=\"reportsv2.php?view=report&amp;id={$Report['ID']}\">reported it</a>";
        } else {
            $ReportLinks = 'Someone reported it';
        }

        if (isset($Types[$CategoryID][$Report['Type']])) {
            $ReportType = $Types[$CategoryID][$Report['Type']];
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

$CanEdit = $Viewer->permitted('torrents_edit')
    || (
        $torrent->uploaderId() == $Viewer->id()
        && !$Viewer->disableWiki()
        && !$torrent->isRemasteredUnknown()
    );
$RegenLink = $Viewer->permitted('users_mod') ? ' <a href="torrents.php?action=regen_filelist&amp;torrentid=' . $TorrentID . '" class="brackets">Regenerate</a>' : '';
$FileTable = '
    <table class="filelist_table">
        <tr class="colhead_dark">
            <td>
                <div class="filelist_title" style="float: left;">File Names' . $RegenLink . '</div>
                <div class="filelist_path" style="float: right;">'
                    . ($torrent->path() ? "/" . $torrent->path() . "/" : '') . '</div>
            </td>
            <td>
                <strong>Size</strong>
            </td>
        </tr>';
$fileList = $torrent->fileList();
foreach ($fileList as $File) {
    $FileInfo = $torMan->splitMetaFilename($File);
    $FileTable .= sprintf("\n<tr><td>%s</td><td class=\"number_column\">%s</td></tr>", $FileInfo['name'], Format::get_size($FileInfo['size']));
}
$FileTable .= "\n</table>";

if ($CategoryID == 1  && ($FirstUnknown || $remasterTuple != $torrent->remasterTuple())) {
?>
                <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?= $GroupID ?> edition group_torrent">
                    <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?= $GroupID ?>, <?= $EditionID ?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= $torrent->edition() ?></strong></td>
                </tr>
<?php
    $EditionID++;
}
$remasterTuple = $torrent->remasterTuple();
?>
                <tr class="torrent_row releases_<?= $tgroup->releaseType() ?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> group_torrent<?=($torrent->isSnatched($Viewer->id()) ? ' snatched_torrent' : '')?>" style="font-weight: normal;" id="torrent<?=($TorrentID)?>">
                    <td>
                        <?= $Twig->render('torrent/action.twig', [
                            'can_fl' => $Viewer->canSpendFLToken($torrent),
                            'key'    => $Viewer->announceKey(),
                            't'      => [
                                'ID'      => $TorrentID,
                                'Size'    => $torrent->size(),
                                'Seeders' => $torrent->seederTotal(),
                            ],
                            'edit'   => $CanEdit,
                            'remove' => $Viewer->permitted('torrents_delete') || $torrent->uploaderId() == $Viewer->id(),
                            'pl'     => true,
                        ]) ?>
                        &raquo; <a href="#" onclick="$('#torrent_<?=($TorrentID)?>').gtoggle(); return false;"><?=
                            implode(' / ', $torrent->labelList()) ?></a>
                    </td>
                    <td class="number_column nobr"><?= Format::get_size($torrent->size()) ?></td>
                    <td class="number_column"><?= number_format($torrent->snatchTotal()) ?></td>
                    <td class="number_column"><?= number_format($torrent->seederTotal()) ?></td>
                    <td class="number_column"><?= number_format($torrent->leecherTotal()) ?></td>
                </tr>
                <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> torrentdetails pad<?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $TorrentID) { ?> hidden<?php } ?>" id="torrent_<?=($TorrentID)?>">
                    <td colspan="5">
                        <blockquote>
                            Uploaded by <?=(Users::format_username($torrent->uploaderId(), false, false, false))?> <?=time_diff($torrent->uploadDate()) ?>
<?php
    if (!$torrent->seederTotal()) {
        $LastActive = $torrent->lastActiveDate();
        if (!is_null($LastActive) && time() - strtotime($LastActive) >= 1209600) {
?>
                                <br /><strong>Last active: <?=time_diff($LastActive);?></strong>
<?php   } else { ?>
                                <br />Last active: <?=time_diff($LastActive);?>
<?php
        }
        if (!is_null($LastActive) && time() - strtotime($LastActive) >= 345678 && time() - strtotime($torrent->lastReseedRequest()) >= 864000) {
?>)
                                <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=($TorrentID)?>&amp;groupid=<?=($GroupID)?>" class="brackets">Request re-seed</a>
<?php
        }
    }
?>
                        </blockquote>
<?php if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <div class="linkbox">
                            <a href="torrents.php?action=masspm&amp;id=<?=($GroupID)?>&amp;torrentid=<?=($TorrentID)?>" class="brackets">Mass PM snatchers</a>
                        </div>
<?php } ?>
                        <div class="linkbox">
<?php if ($Viewer->permitted('site_view_torrent_snatchlist')) { ?>
                            <a href="#" class="brackets tooltip" onclick="show_downloads('<?=($TorrentID)?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View downloaders</a>
                            <a href="#" class="brackets tooltip" onclick="show_snatches('<?=($TorrentID)?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatchers</a>
<?php } ?>
                            <a href="#" class="brackets" onclick="show_peers('<?=($TorrentID)?>', 0); return false;">View seeders</a>
                            <a href="#" class="brackets" onclick="show_files('<?=($TorrentID)?>'); return false;">View contents</a>
<?php if ($Reported) { ?>
                            <a href="#" class="brackets" onclick="show_reported('<?=($TorrentID)?>'); return false;">View report information</a>
<?php } ?>
                        </div>
                        <div id="peers_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="downloads_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="snatches_<?=($TorrentID)?>" class="hidden"></div>
                        <div id="files_<?=($TorrentID)?>" class="hidden"><?=($FileTable)?></div>
<?php if ($Reported) { ?>
                        <div id="reported_<?=($TorrentID)?>" class="hidden"><?=($ReportInfo)?></div>
<?php
    }
    if (!empty($torrent->description())) {
?>
                            <blockquote><?= Text::full_format($torrent->description()) ?></blockquote>
<?php } ?>
                    </td>
                </tr>
        </table>
    </div>

    <form class="create_form" name="report" action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="reportform">
        <div>
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
            <input type="hidden" name="categoryid" value="<?= $CategoryID ?>" />
        </div>

        <h3>Report Information</h3>
        <div class="box pad">
            <table class="layout">
                <tr>
                    <td class="label">Reason:</td>
                    <td>
                        <select id="type" name="type" onchange="ChangeReportType();">
<?php foreach ($TypeList as $Type => $Data) { ?>
            <option value="<?=($Type)?>"><?=($Data['title'])?></option>
<?php } ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p>Fields that contain lists of values (for example, listing more than one track number) should be separated by a space.</p>
            <br />
            <p><strong>Following the below report type specific guidelines will help the moderators deal with your report in a timely fashion. </strong></p>
            <br />

            <div id="dynamic_form">
                <input id="sitelink" type="hidden" name="sitelink" size="50" value="<?=(!empty($_POST['sitelink']) ? display_str($_POST['sitelink']) : '')?>" />
                <input id="image" type="hidden" name="image" size="50" value="<?=(!empty($_POST['image']) ? display_str($_POST['image']) : '')?>" />
                <input id="track" type="hidden" name="track" size="8" value="<?=(!empty($_POST['track']) ? display_str($_POST['track']) : '')?>" />
                <input id="link" type="hidden" name="link" size="50" value="<?=(!empty($_POST['link']) ? display_str($_POST['link']) : '')?>" />
                <input id="extra" type="hidden" name="extra" value="<?=(!empty($_POST['extra']) ? display_str($_POST['extra']) : '')?>" />

                <script type="text/javascript">ChangeReportType();</script>
            </div>
        </div>
    <input type="submit" value="Submit report" />
    </form>
</div>
<?php
View::show_footer();
