<?php

if (!$Viewer->permitted('admin_reports') && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
View::show_header('Other reports stats');

?>
<div class="header">
    <h2>Other reports stats!</h2>
    <div class="linkbox">
        <a href="reports.php">New</a> |
        <a href="reports.php?view=old">Old</a> |
        <a href="reports.php?action=stats">Stats</a>
    </div>
</div>
<div class="thin float_clear">
    <div class="two_columns pad">
<?php
if ($Viewer->permitted('admin_reports')) {
$DB->prepared_query("
    SELECT um.Username,
        count(*) AS Reports
    FROM reports AS r
    INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
    WHERE r.ResolvedTime > now() - INTERVAL 24 HOUR
    GROUP BY um.Username
    ORDER BY Reports DESC
");
$Results = $DB->to_array();
?>
        <h3><strong>Reports resolved in the last 24 hours</strong></h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
    foreach ($Results as $Result) {
        list($Username, $Reports) = $Result;
        if ($Username == $Viewer->username()) {
            $RowClass = ' class="rowa"';
        } else {
            $RowClass = '';
        }
?>
            <tr<?=$RowClass?>>
                <td><?=$Username?></td>
                <td class="number_column"><?=number_format($Reports)?></td>
            </tr>
<?php
    } ?>
        </table>
<?php
$DB->prepared_query("
    SELECT um.Username,
        count(*) AS Reports
    FROM reports AS r
    INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
    WHERE r.ResolvedTime > now() - INTERVAL 1 WEEK
    GROUP BY um.Username
    ORDER BY Reports DESC
");
$Results = $DB->to_array();
?>
        <h3><strong>Reports resolved in the last week</strong></h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
    foreach ($Results as $Result) {
        list($Username, $Reports) = $Result;
        if ($Username == $Viewer->username()) {
            $RowClass = ' class="rowa"';
        } else {
            $RowClass = '';
        }
?>
            <tr<?=$RowClass?>>
                <td><?=$Username?></td>
                <td class="number_column"><?=number_format($Reports)?></td>
            </tr>
<?php
    } ?>
        </table>
<?php
$DB->prepared_query("
    SELECT um.Username,
        count(*) AS Reports
    FROM reports AS r
    INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
    WHERE r.ResolvedTime > now() - INTERVAL 1 MONTH
    GROUP BY um.Username
    ORDER BY Reports DESC
");
$Results = $DB->to_array();
?>
        <h3><strong>Reports resolved in the last month</strong></h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
    foreach ($Results as $Result) {
        list($Username, $Reports) = $Result;
        if ($Username == $Viewer->username()) {
            $RowClass = ' class="rowa"';
        } else {
            $RowClass = '';
        }
?>
            <tr<?=$RowClass?>>
                <td><?=$Username?></td>
                <td class="number_column"><?=number_format($Reports)?></td>
            </tr>
<?php
    } ?>
        </table>
<?php
$DB->prepared_query("
    SELECT um.Username,
        count(*) AS Reports
    FROM reports AS r
    INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
    GROUP BY um.Username
    ORDER BY Reports DESC
");
$Results = $DB->to_array();
?>
        <h3><strong>Reports resolved</strong></h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Reports</td>
            </tr>
<?php
    foreach ($Results as $Result) {
        list($Username, $Reports) = $Result;
        if ($Username == $Viewer->username()) {
            $RowClass = ' class="rowa"';
        } else {
            $RowClass = '';
        }
?>
            <tr<?=$RowClass?>>
                <td><?=$Username?></td>
                <td class="number_column"><?=number_format($Reports)?></td>
            </tr>
<?php
    } ?>
        </table>
<?php
} /* if ($Viewer->permitted('admin_reports')) */ ?>
    </div>
    <div class="two_columns pad">
<?php
    $TrashForumIDs = [12];
    $DB->prepared_query("
        SELECT u.Username,
            count(f.LastPostAuthorID) as Trashed
        FROM forums_topics AS f
        LEFT JOIN users_main AS u ON (u.ID = f.LastPostAuthorID)
        WHERE f.ForumID IN (" . placeholders($TrashForumIDs) . ")
        GROUP BY f.LastPostAuthorID
        ORDER BY Trashed DESC
        LIMIT 30
        ", ...$TrashForumIDs
    );
    $Results = $DB->to_array();
?>
        <h3><strong>Threads trashed since the beginning of time</strong></h3>
        <table class="box border">
            <tr class="colhead">
                <td class="colhead_dark number_column">Place</td>
                <td class="colhead_dark">Username</td>
                <td class="colhead_dark number_column">Trashed</td>
            </tr>
<?php
    $i = 1;
    foreach ($Results as $Result) {
        [$Username, $Trashed] = $Result;
        if ($Username == $Viewer->username()) {
            $RowClass = ' class="rowa"';
        } else {
            $RowClass = '';
        }
?>
            <tr<?=$RowClass?>>
                <td class="number_column"><?=$i?></td>
                <td><?=$Username?></td>
                <td class="number_column"><?=number_format($Trashed)?></td>
            </tr>
<?php
        $i++;
    }
?>
        </table>
    </div>
</div>
<?php

View::show_footer();
