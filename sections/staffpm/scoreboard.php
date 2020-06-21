<?php
if (!check_perms('admin_staffpm_stats')) { error(403); }

include(SERVER_ROOT.'/sections/staff/functions.php');

View::show_header('Staff Inbox');

$View   = isset($_REQUEST['view']) ? $_REQUEST['view'] : 'staff';
$Action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'stats';
?>
    <div class="thin">
        <div class="linkbox">
<?php
if ($IsStaff) { ?>
            <a href="staffpm.php" class="brackets">View your unanswered</a>
<?php
} ?>
            <a href="staffpm.php?view=unanswered" class="brackets">View all unanswered</a>
            <a href="staffpm.php?view=open" class="brackets">View unresolved</a>
            <a href="staffpm.php?view=resolved" class="brackets">View resolved</a>
            <a href="staffpm.php?action=scoreboard&amp;view=user" class="brackets">View user scoreboard</a>
            <a href="staffpm.php?action=scoreboard&amp;view=staff" class="brackets">View staff scoreboard</a>
<?php
if ($IsFLS && !$IsStaff) { ?>
            <span class="tooltip" title="This is the inbox where replies to Staff PMs you have sent are."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php
} ?>
        </div>
        <div class="head">Statistics</div>
        <div class="box pad">
        <table>
        <tr>
            <td style="width: 50%; vertical-align: top;">
<?php

$SupportStaff = get_support();
list($FrontLineSupport, $Staff) = $SupportStaff;
$SupportStaff = array_merge($FrontLineSupport, ...array_values($Staff));
$SupportStaff = array_column($SupportStaff, 'ID');

if ($View != 'staff') {
    $IN    = "NOT IN";
    $COL   = "PMs";
    $EXTRA = "(    SELECT COUNT(spc.ID)
                FROM staff_pm_conversations AS spc
                WHERE spc.UserID=um.ID
                AND spc.Date > ?)";
} else {
    $IN    = "IN";
    $COL   = "Resolved";
    $EXTRA = "(    SELECT COUNT(spc.ID)
                FROM staff_pm_conversations AS spc
                WHERE spc.ResolverID=um.ID
                AND spc.Status = 'Resolved'
                AND spc.Date > ?)";
}

$BaseSQL = sprintf("SELECT
                        um.ID,
                        um.Username,
                        COUNT(spm.ID) AS Num,
                        %s AS Extra
                    FROM staff_pm_messages AS spm
                    INNER JOIN users_main AS um ON um.ID=spm.UserID
                    INNER JOIN permissions p ON p.ID = um.PermissionID
                    WHERE spm.SentDate > ? AND p.Level <= ? AND um.ID %s (%s)
                    GROUP BY spm.UserID
                    ORDER BY Num DESC
                    LIMIT 50", $EXTRA, $IN,
                    placeholders($SupportStaff));

$DB->prepared_query($BaseSQL, \Gazelle\Util\Time::timeOffset(-3600 * 24), \Gazelle\Util\Time::timeOffset(-3600 * 24), $LoggedUser['Class'], ...$SupportStaff);
$Results = $DB->to_array();

?>
            <strong>Inbox actions in the last 24 hours</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php
foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php
} ?>
            </table>
            <br /><br />
<?php

$DB->prepared_query($BaseSQL, \Gazelle\Util\Time::timeOffset(-3600 * 24 * 7), \Gazelle\Util\Time::timeOffset(-3600 * 24 * 7), $LoggedUser['Class'], ...$SupportStaff);
$Results = $DB->to_array();

?>
            <strong>Inbox actions in the last week</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php
foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php
} ?>
            </table>
        <br /><br />
<?php

$DB->prepared_query($BaseSQL, \Gazelle\Util\Time::timeOffset(-3600 * 24 * 30), \Gazelle\Util\Time::timeOffset(-3600 * 24 * 30), $LoggedUser['Class'], ...$SupportStaff);
$Results = $DB->to_array();

?>
            <strong>Inbox actions in the last month</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php
foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php
} ?>
            </table>
        </td>
        <td style="vertical-align: top;">
<?php

$DB->prepared_query($BaseSQL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', $LoggedUser['Class'], ...$SupportStaff);
$Results = $DB->to_array();

?>
            <strong>Inbox actions total</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php
foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="/reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php
} ?>
            </table>
        </td></tr>
        </table>
        </div>
    </div>

<?php
View::show_footer();
