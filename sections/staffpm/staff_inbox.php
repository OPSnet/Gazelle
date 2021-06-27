<?php

View::show_header('Staff Inbox');

$View = display_str(empty($_GET['view']) ? '' : $_GET['view']);

// Setup for current view mode
$SortStr = 'IF(AssignedToUser = ' . $Viewer->id() . ', 0, 1) ASC, ';
switch ($View) {
    case 'unanswered':
        $ViewString = 'Unanswered';
        $Status = "Unanswered";
        break;
    case 'open':
        $ViewString = 'Unresolved';
        $Status = "Open', 'Unanswered";
        $SortStr = '';
        break;
    case 'resolved':
        $ViewString = 'Resolved';
        $Status = "Resolved";
        $SortStr = '';
        break;
    case 'my':
        $ViewString = 'Your Unanswered';
        $Status = "Unanswered";
        break;
    default:
        $Status = "Unanswered";
        if ($Viewer->isStaff()) {
            $ViewString = 'Your Unanswered';
        } else {
            // FLS
            $ViewString = 'Unanswered';
        }
        break;
}
$UserLevel = $Viewer->effectiveClass();
$WhereCondition = "
    WHERE (spc.Level <= $UserLevel OR spc.AssignedToUser = '".$Viewer->id()."')
        AND spc.Status IN ('$Status')";

$Classes = (new Gazelle\Manager\User)->classList();
if ($ViewString == 'Your Unanswered') {
    if ($UserLevel >= $Classes[MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[MOD]['Level'];
    } else if ($UserLevel == $Classes[FORUM_MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[FORUM_MOD]['Level'];
    }
}

$Sections = [
    'unanswered' => "All unanswered",
    'open' => "Unresolved",
    'resolved' => 'Resolved',
];
if ($Viewer->isStaff()) {
    $Sections = ['' => 'Your unanswered'] + $Sections;
}

// Start page
?>
<div class="thin">
    <div class="header">
        <h2><?=$ViewString?> Staff PMs</h2>
        <div class="linkbox">
<?php
foreach ($Sections as $Section => $Text) {
    if ($Section == 'unanswered') {
        $Text .= '(' . $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE Status IN ('Unanswered')
                AND (Level <= ? OR AssignedToUser = ?)
            ", $UserLevel, $Viewer->id()
        ) . ')';
    } elseif ($Section == 'open') {
        $Text .= '(' . $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE Status IN ('Open', 'Unanswered')
                AND (Level <= ? OR AssignedToUser = ?)
            ", $UserLevel, $Viewer->id()
        ) . ')';
    }
    if ($Section == $View) {
        $Text = "<strong>$Text</strong>";
    }

    $Section = ($Section) ? "?view=$Section" : '';

    // Make sure the trailing space in this output remains.
    ?><a href="staffpm.php<?= $Section ?>" class="brackets"><?= $Text ?></a>&nbsp;<?php
}

if (check_perms('admin_staffpm_stats')) { ?>
            <a href="staffpm.php?action=scoreboard&amp;view=user" class="brackets">View user scoreboard</a>
            <a href="staffpm.php?action=scoreboard&amp;view=staff" class="brackets">View staff scoreboard</a>
<?php
}
    if ($Viewer->isFLS()) { ?>
            <span class="tooltip" title="This is the inbox where replies to Staff PMs you have sent are."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php    } ?>
        </div>
    </div>
    <br />
    <br />
<?php
[$Page, $Limit] = Format::page_limit(MESSAGES_PER_PAGE);

$NumResults = $DB->scalar("
    SELECT count(*)
    FROM staff_pm_conversations AS spc
    LEFT JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
    $WhereCondition
");
// Get messages
$StaffPMs = $DB->prepared_query("
    SELECT spc.ID,
        spc.Subject,
        spc.UserID,
        spc.Status,
        spc.Level,
        spc.AssignedToUser,
        spc.Date,
        spc.Unread,
        count(spm.ID) AS NumReplies,
        spc.ResolverID
    FROM staff_pm_conversations AS spc
    LEFT JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
    $WhereCondition
    GROUP BY spc.ID
    ORDER BY $SortStr spc.Date DESC
    LIMIT $Limit
");

$Pages = Format::get_pages($Page, $NumResults, MESSAGES_PER_PAGE, 9);
?>
    <div class="linkbox">
        <?=$Pages?>
    </div>
    <div class="box pad" id="inbox">
<?php if (!$DB->has_results()) { ?>
        <h2>No messages</h2>
<?php
} else {
    // Messages, draw table
    if ($ViewString != 'Resolved' && $Viewer->isStaff()) {
        // Open multiresolve form
?>
        <form class="manage_form" name="staff_messages" method="post" action="staffpm.php" id="messageform">
            <input type="hidden" name="action" value="multiresolve" />
            <input type="hidden" name="view" value="<?=strtolower($View)?>" />
<?php } ?>
            <table class="message_table<?=($ViewString != 'Resolved' && $Viewer->isStaff()) ? ' checkboxes' : '' ?>">
                <tr class="colhead">
<?php     if ($ViewString != 'Resolved' && $Viewer->isStaff()) { ?>
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
<?php     } ?>
                    <td>Subject</td>
                    <td>Sender</td>
                    <td>Date</td>
                    <td>Assigned to</td>
                    <td>Replies</td>
<?php    if ($ViewString == 'Resolved') { ?>
                    <td>Resolved by</td>
<?php    } ?>
                </tr>
<?php
    // List messages
    $ClassLevels = (new Gazelle\Manager\User)->classLevelList();
    $Row = 'a';
    while ([$ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread, $NumReplies, $ResolverID] = $DB->next_record()) {
        $Row = $Row === 'a' ? 'b' : 'a';

        // Get assigned
        if ($AssignedToUser != '') {
            $Assigned = Users::format_username($AssignedToUser, true, true, true, true);
        } else {
            // Assigned to class
            $Assigned = ($Level == 0) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
            // No + on Sysops
            if ($Assigned != 'Sysop') {
                $Assigned .= '+';
            }
        }
?>
                <tr class="row<?= $Row ?>">
<?php         if ($ViewString != 'Resolved' && $Viewer->isStaff()) { ?>
                    <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
<?php         } ?>
                    <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?= Users::format_username($UserID, true, true, true, true) ?></td>
                    <td><?= time_diff($Date, 2, true) ?></td>
                    <td><?= $Assigned ?></td>
                    <td><?= max(0, $NumReplies - 1) ?></td>
<?php        if ($ViewString == 'Resolved') { ?>
                    <td><?= Users::format_username($ResolverID, true, true, true, true) ?></td>
<?php        } ?>
                </tr>
<?php
        $DB->set_query_id($StaffPMs);
    } //while
?>
            </table>
<?php     if ($ViewString != 'Resolved' && $Viewer->isStaff()) { ?>
            <div class="submit_div">
                <input type="submit" value="Resolve selected" />
            </div>
        </form>
<?php
    }
} // $DB->has_results()
?>
    </div>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
View::show_footer();
