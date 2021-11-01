<?php

if (!$Viewer->permitted('admin_staffpm_stats')) {
    error(403);
}

$View   = ($_REQUEST['view'] ?? 'staff');
$Action = ($_REQUEST['action'] ?? 'stats');

$userMan = new Gazelle\Manager\User;
$SupportStaff = array_merge(array_keys($userMan->flsList()), array_keys($userMan->staffList()));

if ($View != 'staff') {
    $IN    = "NOT IN";
    $COL   = "PMs";
    $EXTRA = "(SELECT count(*)
                FROM staff_pm_conversations AS spc
                WHERE spc.UserID=um.ID
                AND spc.Date > now() - INTERVAL ? DAY)";
} else {
    $IN    = "IN";
    $COL   = "Resolved";
    $EXTRA = "(SELECT count(*)
                FROM staff_pm_conversations AS spc
                WHERE spc.ResolverID=um.ID
                AND spc.Status = 'Resolved'
                AND spc.Date > now() - INTERVAL ? DAY)";
}

$BaseSQL = sprintf("
    SELECT um.ID,
        um.Username,
        count(*) AS Num,
        %s AS Extra
    FROM staff_pm_messages AS spm
    INNER JOIN users_main AS um ON (um.ID = spm.UserID)
    INNER JOIN permissions p ON (p.ID = um.PermissionID)
    WHERE spm.SentDate > now() - INTERVAL ? DAY AND p.Level <= ? AND um.ID %s (%s)
    GROUP BY spm.UserID
    ORDER BY Num DESC
    LIMIT 50
    ", $EXTRA, $IN, placeholders($SupportStaff)
);

$DB->prepared_query($BaseSQL, 1, 1, $Viewer->classLevel(), ...$SupportStaff);
$Results = $DB->to_array();

View::show_header('Staff Inbox');
?>
    <div class="thin">
        <div class="linkbox">
<?php if ($Viewer->isStaff()) { ?>
            <a href="staffpm.php" class="brackets">View your unanswered</a>
<?php } ?>
            <a href="staffpm.php?view=unanswered" class="brackets">View all unanswered</a>
            <a href="staffpm.php?view=open" class="brackets">View unresolved</a>
            <a href="staffpm.php?view=resolved" class="brackets">View resolved</a>
            <a href="staffpm.php?action=scoreboard&amp;view=user" class="brackets">View user scoreboard</a>
            <a href="staffpm.php?action=scoreboard&amp;view=staff" class="brackets">View staff scoreboard</a>
<?php if ($Viewer->isFLS()) { ?>
            <span class="tooltip" title="The Staff PMs that you created are here."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php } ?>
        </div>
        <div class="head">Statistics</div>
        <div class="box pad">
        <table>
        <tr>
            <td style="width: 50%; vertical-align: top;">
            <strong>Inbox actions in the last 24 hours</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php foreach ($Results as list($UserID, $Username, $Num, $Extra)) { ?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php } ?>
            </table>
            <br /><br />
<?php
$DB->prepared_query($BaseSQL, 7, 7, $Viewer->classLevel(), ...$SupportStaff);
$Results = $DB->to_array();
?>
            <strong>Inbox actions in the last week</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php foreach ($Results as list($UserID, $Username, $Num, $Extra)) { ?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php } ?>
            </table>
        <br /><br />
<?php
$DB->prepared_query($BaseSQL, 30, 30, $Viewer->classLevel(), ...$SupportStaff);
$Results = $DB->to_array();
?>
            <strong>Inbox actions in the last month</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php foreach ($Results as list($UserID, $Username, $Num, $Extra)) { ?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php } ?>
            </table>
        </td>
        <td style="vertical-align: top;">
<?php
$DB->prepared_query($BaseSQL, 365000, 365000, $Viewer->classLevel(), ...$SupportStaff);
$Results = $DB->to_array();
?>
            <strong>Inbox actions total</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php foreach ($Results as list($UserID, $Username, $Num, $Extra)) { ?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php } ?>
            </table>
        </td></tr>
        </table>
        </div>
    </div>
<?php
View::show_footer();
