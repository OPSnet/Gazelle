<?php
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

function build_torrents_table($GroupID, $GroupName, $GroupCategoryID, $ReleaseType, $TorrentList, $Types) {
    // TODO: replace this horror with Twig
    global $Cache, $DB, $LoggedUser, $Twig;
    $torMan = new Gazelle\Manager\Torrent;

    $LastRemasterYear = '-';
    $LastRemasterTitle = '';
    $LastRemasterRecordLabel = '';
    $LastRemasterCatalogueNumber = '';

    $EditionID = 0;
    $torMan = new Gazelle\Manager\Torrent;
    // foreach ($TorrentList as $Torrent) {
        [$TorrentID, $Media, $Format, $Encoding, $Remastered, $RemasterYear,
        $RemasterTitle, $RemasterRecordLabel, $RemasterCatalogueNumber, $Scene,
        $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $FileCount, $Size, $Seeders, $Leechers,
        $Snatched, $FreeTorrent, $TorrentTime, $Description, $FileList,
        $FilePath, $UserID, $LastActive, $InfoHash, $BadTags, $BadFolders, $BadFiles,
        $MissingLineage, $CassetteApproved, $LossymasterApproved, $LossywebApproved, $LastReseedRequest,
        $HasFile, $LogCount, $PersonalFL, $IsSnatched] = array_values($TorrentList);

        $FirstUnknown = ($Remastered && !$RemasterYear);

        $Reported = false;
        $Reports = Torrents::get_reports($TorrentID);
        $NumReports = count($Reports);

        if ($NumReports > 0) {
            require_once(__DIR__ . '/../reports/array.php');
            $Reported = true;
            $ReportInfo = '
            <table class="reportinfo_table">
                <tr class="colhead_dark" style="font-weight: bold;">
                    <td>This torrent has '.$NumReports.' active report'.plural($NumReports).":</td>
                </tr>";

            foreach ($Reports as $Report) {
                if (check_perms('admin_reports')) {
                    $ReporterID = $Report['ReporterID'];
                    $ReporterName = $userMan->findById($ReporterID)->username();
                    $ReportLinks = "<a href=\"user.php?id=$ReporterID\">$ReporterName</a> <a href=\"reportsv2.php?view=report&amp;id={$Report['ID']}\">reported it</a>";
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
            $FileListSplit = explode("\n", $FileList);
            foreach ($FileListSplit as $File) {
                $FileInfo = $torMan->splitMetaFilename($File);
                $FileTable .= sprintf("\n<tr><td>%s</td><td class=\"number_column\">%s</td></tr>", $FileInfo['name'], Format::get_size($FileInfo['size']));
            }
        $FileTable .= "\n</table>";

        $ExtraInfo = [];
        // similar to Torrents::torrent_info()
        if ($Format) {
            $ExtraInfo[] = display_str($Format);
        }
        if ($Encoding) {
            $ExtraInfo[] = display_str($Encoding);
        }
        if ($HasLog) {
            $ExtraInfo[] = "Log" . ($HasLog && $HasLogDB ? " (${LogScore}%)" : '');
        }
        if ($HasCue) {
            $ExtraInfo[] = "Cue";
        }
        if ($Scene) {
            $ExtraInfo[] = "Scene";
        }
        if (!$ExtraInfo) {
            $ExtraInfo[] = $GroupName;
        }
        if ($IsSnatched) {
            $ExtraInfo[] = Format::torrent_label('Snatched!');
        }
        if ($FreeTorrent == '1') {
            $ExtraInfo[] = Format::torrent_label('Freeleech!');
        }
        if ($FreeTorrent == '2') {
            $ExtraInfo[] = Format::torrent_label('Neutral Leech!');
        }
        if ($PersonalFL) {
            $ExtraInfo[] = Format::torrent_label('Personal Freeleech!');
        }
        if ($Reported) {
            $ExtraInfo[] = Format::torrent_label('Reported');
        }
        if ($HasLog && $HasLogDB && !$LogChecksum) {
            $ExtraInfo[] = Format::torrent_label('Bad/Missing Checksum');
        }
        if ($BadTags) {
            $ExtraInfo[] = Format::torrent_label('Bad Tags');
        }
        if ($BadFolders) {
            $ExtraInfo[] = Format::torrent_label('Bad Folders');
        }
        if ($MissingLineage) {
            $ExtraInfo[] = Format::torrent_label('Missing Lineage');
        }
        if ($CassetteApproved) {
            $ExtraInfo[] = Format::torrent_label('Cassette Approved');
        }
        if ($LossymasterApproved) {
            $ExtraInfo[] = Format::torrent_label('Lossy Master Approved');
        }
        if ($LossywebApproved) {
            $ExtraInfo[] = Format::torrent_label('Lossy WEB Approved');
        }
        if ($BadFiles) {
            $ExtraInfo[] = Format::torrent_label('Bad File Names');
        }
        $ExtraInfo = count($ExtraInfo) ? implode(' / ', $ExtraInfo) : '';

        if ($GroupCategoryID == 1
            && ($RemasterTitle != $LastRemasterTitle
            || $RemasterYear != $LastRemasterYear
            || $RemasterRecordLabel != $LastRemasterRecordLabel
            || $RemasterCatalogueNumber != $LastRemasterCatalogueNumber
            || $FirstUnknown
            || $Media != $LastMedia)
        ) {

            $EditionID++;
            $info = $torMan->findById($TorrentID)->info();
?>
                <tr class="releases_<?=($ReleaseType)?> groupid_<?=($GroupID)?> edition group_torrent">
                    <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=($GroupID)?>, <?=($EditionID)?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($info)?></strong></td>
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
                        <?= $Twig->render('torrent/action.twig', [
                            'can_fl' => Torrents::can_use_token($TorrentList),
                            'key'    => $LoggedUser['torrent_pass'],
                            't'      => $TorrentList,
                            'edit'   => $CanEdit,
                            'remove' => check_perms('torrents_delete') || $UserID == $LoggedUser['ID'],
                            'pl'     => true,
                        ]) ?>
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
            if (!is_null($LastActive) && time() - strtotime($LastActive) >= 1209600) { ?>
                                <br /><strong>Last active: <?=time_diff($LastActive);?></strong>
<?php       } else { ?>
                                <br />Last active: <?=time_diff($LastActive);?>
<?php       }
            if (!is_null($LastActive) && time() - strtotime($LastActive) >= 345678 && time() - strtotime($LastReseedRequest) >= 864000) { ?>)
                                <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=($TorrentID)?>&amp;groupid=<?=($GroupID)?>" class="brackets">Request re-seed</a>
<?php       }
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
        if (!empty($Description)) { ?>
                            <blockquote><?= Text::full_format($Description) ?></blockquote>
<?php   } ?>
                    </td>
                </tr>
<?php
    // }
}

$reportMan = new Gazelle\Manager\ReportV2;
$torMan = new Gazelle\Manager\Torrent;
$userMan = new Gazelle\Manager\User;
$Types = $reportMan->types();

//If we're not coming from torrents.php, check we're being returned because of an error.
if (!isset($_GET['id']) || !is_number($_GET['id'])) {
    if (!isset($Err)) {
        error(404);
    }
} else {
    $TorrentID = (int)$_GET['id'];
    [$CategoryID, $GroupID] = $DB->row("
        SELECT tg.CategoryID, t.GroupID
        FROM torrents_group AS tg
        LEFT JOIN torrents AS t ON (t.GroupID = tg.ID)
        WHERE t.ID = ?
        ", $TorrentID
    );
    if (empty($CategoryID) || empty($GroupID)) {
        // Deleted torrent
        header("Location: log.php?search=Torrent+" . $TorrentID);
        exit;
    }
    $Artists = Artists::get_artist($GroupID);
    $torrent = (new Gazelle\Manager\Torrent)->findById($TorrentID);
    $TorrentList = $torrent->info();
    $GroupDetails = $torrent->group()->info();
    // Group details
    [$WikiBody,, $GroupID, $GroupName, $GroupYear,,, $ReleaseType, $GroupCategoryID,,
        $GroupVanityHouse,,,,,, $GroupFlags] = array_values($GroupDetails);

    $DisplayName = $GroupName;
    $AltName = $GroupName; // Goes in the alt text of the image
    $Title = $GroupName; // goes in <title>
    $WikiBody = Text::full_format($WikiBody);

    //Get the artist name, group name etc.
    $Artists = Artists::get_artist($GroupID);
    if ($Artists) {
        $DisplayName = '<span dir="ltr">' . Artists::display_artists($Artists, true) . "<a href=\"torrents.php?torrentid=$TorrentID\">$DisplayName</a></span>";
        $AltName = display_str(Artists::display_artists($Artists, false)) . $AltName;
        $Title = $AltName;
    }
    if ($GroupYear > 0) {
        $DisplayName .= " [$GroupYear]";
        $AltName .= " [$GroupYear]";
        $Title .= " [$GroupYear]";
    }
    if ($GroupVanityHouse) {
        $DisplayName .= ' [Vanity House]';
        $AltName .= ' [Vanity House]';
    }
    if ($GroupCategoryID == 1) {
        $name = (new Gazelle\ReleaseType)->findNameById($ReleaseType);
        $DisplayName .= " [$name] ";
        $AltName .= " [$name] ";
    }
}

$urlStem = STATIC_SERVER . '/styles/' . $LoggedUser['StyleName'] . '/images';

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

View::show_header('Report', 'reportsv2,browse,torrent,bbcode');
?>

<div class="thin">
    <div class="header">
        <h2>Report a torrent</h2>
    </div>
    <div class="header">
        <h3><?=$DisplayName?></h3>
    </div>
    <div class="thin">
        <table class="torrent_table details<?=($GroupFlags['IsSnatched'] ? ' snatched' : '')?>" id="torrent_details">
            <tr class="colhead_dark">
                <td width="80%"><strong>Reported torrent</strong></td>
                <td><strong>Size</strong></td>
                <td class="sign snatches"><img src="<?= $urlStem ?>/snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>/seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>/leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php build_torrents_table($GroupID, $GroupName, $GroupCategoryID, $ReleaseType, $TorrentList, $Types); ?>
        </table>
    </div>

    <form class="create_form" name="report" action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="reportform">
        <div>
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
            <input type="hidden" name="categoryid" value="<?=$CategoryID?>" />
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
<?php
    /*
     * THIS IS WHERE SEXY AJAX COMES IN
     * The following malarky is needed so that if you get sent back here, the fields are filled in.
     */
?>
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
