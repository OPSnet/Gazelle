<?php
/*
 * This page is to outline all of the views built into reports v2.
 * It's used as the main page as it also lists the current reports by type
 * and the current in-progress reports by staff member.
 * All the different views are self explanatory by their names.
 */
if (!check_perms('admin_reports')) {
    error(403);
}

View::show_header('Reports V2', ['js' => 'reportsv2']);

//Grab owner's ID, just for examples
[$ownerId, $owner] = $DB->row("
    SELECT ID, Username
    FROM users_main
    WHERE ID = ?
", $Viewer->id());
$owner = display_str($owner);
$reportMan = new Gazelle\Manager\ReportV2;
$userMan = new Gazelle\Manager\User;
?>
<div class="header">
    <h2>Reports V2 Information</h2>
<?php require_once('header.php'); ?>
</div>
<div class="thin float_clear">
    <div class="two_columns pad">
        <h3>Reports resolved in the last 24 hours</h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
$list = $reportMan->resolvedLastDay();
foreach ($list as $summary) {
    [$userId, $username, $count] = $summary;
?>
            <tr<?= $username == $Viewer->username() ? ' class="rowa"' : '' ?>>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$userId?>"><?=$username?></a></td>
                <td class="number_column"><?=number_format($count)?></td>
            </tr>
<?php } ?>
        </table>

        <h3>Reports resolved in the last week</h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
$list = $reportMan->resolvedLastWeek();
foreach ($list as $summary) {
    [$userId, $username, $count] = $summary;
?>
            <tr<?= $username == $Viewer->username() ? ' class="rowa"' : '' ?>>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$userId?>"><?=$username?></a></td>
                <td class="number_column"><?=number_format($count)?></td>
            </tr>
<?php } ?>
        </table>

        <h3>Reports resolved in the last month</h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
$list = $reportMan->resolvedLastMonth();
foreach ($list as $summary) {
    [$userId, $username, $count] = $summary;
?>
            <tr<?= $username == $Viewer->username() ? ' class="rowa"' : '' ?>>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$userId?>"><?=$username?></a></td>
                <td class="number_column"><?=number_format($count)?></td>
            </tr>
<?php } ?>
        </table>

        <h3>Total reports resolved</h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
$list = $reportMan->resolvedSummary();
foreach ($list as $summary) {
    [$userId, $username, $count] = $summary;
?>
            <tr<?= $username == $Viewer->username() ? ' class="rowa"' : '' ?>>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$userId?>"><?=$username?></a></td>
                <td class="number_column"><?=number_format($count)?></td>
            </tr>
<?php } ?>
        </table>
        <h3>Different view modes by person</h3>
        <div class="box pad">
            <strong>By ID of torrent reported:</strong>
            <ul>
                <li>
                    Reports of torrents with ID = 1
                </li>
                <li>
                    <a href="reportsv2.php?view=torrent&amp;id=1"><?=SITE_URL?>/reportsv2.php?view=torrent&amp;id=1</a>
                </li>
            </ul>
            <strong>By group ID of torrent reported:</strong>
            <ul>
                <li>
                    Reports of torrents within the group with ID = 1
                </li>
                <li>
                    <a href="reportsv2.php?view=group&amp;id=1"><?=SITE_URL?>/reportsv2.php?view=group&amp;id=1</a>
                </li>
            </ul>
            <strong>By report ID:</strong>
            <ul>
                <li>
                    The report with ID = 1
                </li>
                <li>
                    <a href="reportsv2.php?view=report&amp;id=1"><?=SITE_URL?>/reportsv2.php?view=report&amp;id=1</a>
                </li>
            </ul>
            <strong>By reporter ID:</strong>
            <ul>
                <li>
                    Reports created by <?=$owner?>
                </li>
                <li>
                    <a href="reportsv2.php?view=reporter&amp;id=<?=$ownerId?>"><?=SITE_URL?>/reportsv2.php?view=reporter&amp;id=<?=$ownerId?></a>
                </li>
            </ul>
            <strong>By uploader ID:</strong>
            <ul>
                <li>
                    Reports for torrents uploaded by <?=$owner?>
                </li>
                <li>
                    <a href="reportsv2.php?view=uploader&amp;id=<?=$ownerId?>"><?=SITE_URL?>/reportsv2.php?view=uploader&amp;id=<?=$ownerId?></a>
                </li>
            </ul>
            <strong>By resolver ID:</strong>
            <ul>
                <li>
                    Reports for torrents resolved by <?=$owner?>
                </li>
                <li>
                    <a href="reportsv2.php?view=resolver&amp;id=<?=$ownerId?>"><?=SITE_URL?>/reportsv2.php?view=resolver&amp;id=<?=$ownerId?></a>
                </li>
            </ul>
            <strong>User the search feature for anything more specific.</strong>
        </div>
    </div>
    <div class="two_columns pad">

        <h3>Currently assigned reports by staff member</h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Staff Member</td>
                <td class="colhead_dark number_column">Current Count</td>
            </tr>
<?php
$list = $reportMan->inProgressSummary();
foreach ($list as $summary) {
?>
            <tr class="<?= $summary['user_id'] == $Viewer->id() ? 'rowa' : 'rowb' ?>">
                <td>
                    <a href="reportsv2.php?view=staff&amp;id=<?=$summary['user_id']?>"><?=display_str($userMan->findById($summary['user_id'])->username())?>'s reports</a>
                </td>
                <td class="number_column"><?=number_format($summary['nr'])?></td>
            </tr>
<?php } ?>
        </table>
        <h3>Different view modes by report type</h3>
<?php
$list = $reportMan->newSummary();
$Types = $reportMan->types();
if (!empty($list)) {
?>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Type</td>
                <td class="colhead_dark number_column">Current Count</td>
            </tr>
<?php
        foreach ($list as $summary) {
            //Ugliness
            foreach ($Types as $Category) {
                if (!empty($Category[$summary['Type']])) {
                    $title = $Category[$summary['Type']]['title'];
                    break;
                }
            }
?>
            <tr<?=$title === 'Urgent' ? ' class="rowa" style="font-weight: bold;"' : ''?>>
                <td>
                    <a href="reportsv2.php?view=type&amp;id=<?=display_str($summary['Type'])?>"><?=display_str($title)?></a>
                </td>
                <td class="number_column">
                    <?=number_format($summary['Count'])?>
                </td>
            </tr>
<?php
        }
    }
?>
        </table>
    </div>
</div>
<?php
View::show_footer();
