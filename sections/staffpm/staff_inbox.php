<?php

View::show_header('Staff Inbox');

$View = display_str(empty($_GET['view']) ? '' : $_GET['view']);
$UserLevel = $LoggedUser['EffectiveClass'];


$LevelCap = Permissions::get_level_cap();

// Setup for current view mode
$SortStr = 'IF(AssignedToUser = '.$LoggedUser['ID'].', 0, 1) ASC, ';
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
        if ($UserLevel >= $Classes[MOD]['Level'] || $UserLevel == $Classes[FORUM_MOD]['Level']) {
            $ViewString = 'Your Unanswered';
        } else {
            // FLS
            $ViewString = 'Unanswered';
        }
        break;
}

$WhereCondition = "
    WHERE (LEAST($LevelCap, spc.Level) <= $UserLevel OR spc.AssignedToUser = '".$LoggedUser['ID']."')
        AND spc.Status IN ('$Status')";

if ($ViewString == 'Your Unanswered') {
    if ($UserLevel >= $Classes[MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[MOD]['Level'];
    } else if ($UserLevel == $Classes[FORUM_MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[FORUM_MOD]['Level'];
    }
}

$Row = 'a';

// Start page
?>
<div class="thin">
    <div class="header">
        <h2><?=$ViewString?> Staff PMs</h2>
        <div class="linkbox">
<?php
$Sections = [
    'unanswered' => "All unanswered",
    'open' => "Unresolved",
    'resolved' => 'Resolved',
];
if ($IsStaff) {
    $Sections = ['' => 'Your unanswered'] + $Sections;
}

foreach ($Sections as $Section => $Text) {
    if ($Section == 'unanswered') {
        $AllUnansweredStaffPMCount = $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE (least(?, Level) <= ? OR AssignedToUser = ?)
                AND Status IN ('Unanswered')
        ", $LevelCap, $UserLevel, $LoggedUser['ID']);
        $Text .= " ($AllUnansweredStaffPMCount)";
    }
    if ($Section == 'open') {
        $UnresolvedStaffPMCount = $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE (least(?, Level) <= ? OR AssignedToUser = ?)
                AND Status IN ('Open', 'Unanswered')
        ", $LevelCap, $UserLevel, $LoggedUser['ID']);
        $Text .= " ($UnresolvedStaffPMCount)";
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
    if ($IsFLS && !$IsStaff) { ?>
            <span class="tooltip" title="This is the inbox where replies to Staff PMs you have sent are."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php    } ?>
        </div>
    </div>
    <br />
    <br />
<?php
list($Page, $Limit) = Format::page_limit(MESSAGES_PER_PAGE);
// Get messages
$StaffPMs = $DB->query("
    SELECT
        SQL_CALC_FOUND_ROWS
        spc.ID,
        spc.Subject,
        spc.UserID,
        spc.Status,
        spc.Level,
        spc.AssignedToUser,
        spc.Date,
        spc.Unread,
        COUNT(spm.ID) AS NumReplies,
        spc.ResolverID
    FROM staff_pm_conversations AS spc
    JOIN staff_pm_messages spm ON spm.ConvID = spc.ID
    $WhereCondition
    GROUP BY spc.ID
    ORDER BY $SortStr spc.Date DESC
    LIMIT $Limit
");

$DB->query('SELECT FOUND_ROWS()');
list($NumResults) = $DB->next_record();
$DB->set_query_id($StaffPMs);

$Pages = Format::get_pages($Page, $NumResults, MESSAGES_PER_PAGE, 9);
?>
    <div class="linkbox">
        <?=$Pages?>
    </div>
    <div class="box pad" id="inbox">
<?php

if (!$DB->has_results()) {
    // No messages
?>
        <h2>No messages</h2>
<?php

} else {
    // Messages, draw table
    if ($ViewString != 'Resolved' && $IsStaff) {
        // Open multiresolve form
?>
        <form class="manage_form" name="staff_messages" method="post" action="staffpm.php" id="messageform">
            <input type="hidden" name="action" value="multiresolve" />
            <input type="hidden" name="view" value="<?=strtolower($View)?>" />
<?php
    }

    // Table head
?>
            <table class="message_table<?=($ViewString != 'Resolved' && $IsStaff) ? ' checkboxes' : '' ?>">
                <tr class="colhead">
<?php     if ($ViewString != 'Resolved' && $IsStaff) { ?>
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
<?php     } ?>
                    <td width="50%">Subject</td>
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
    while (list($ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread, $NumReplies, $ResolverID) = $DB->next_record()) {
        $Row = $Row === 'a' ? 'b' : 'a';
        $RowClass = "row$Row";

        $UserStr = Users::format_username($UserID, true, true, true, true);

        // Get assigned
        if ($AssignedToUser == '') {
            // Assigned to class
            $Assigned = ($Level == 0) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
            // No + on Sysops
            if ($Assigned != 'Sysop') {
                $Assigned .= '+';
            }

        } else {
            // Assigned to user
            $Assigned = Users::format_username($AssignedToUser, true, true, true, true);

        }

        // Get resolver
        if ($ViewString == 'Resolved') {
            $ResolverStr = Users::format_username($ResolverID, true, true, true, true);
        }

        // Table row
?>
                <tr class="<?=$RowClass?>">
<?php         if ($ViewString != 'Resolved' && $IsStaff) { ?>
                    <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
<?php         } ?>
                    <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?=$UserStr?></td>
                    <td><?=time_diff($Date, 2, true)?></td>
                    <td><?=$Assigned?></td>
                    <td><?=$NumReplies - 1?></td>
<?php        if ($ViewString == 'Resolved') { ?>
                    <td><?=$ResolverStr?></td>
<?php        } ?>
                </tr>
<?php

        $DB->set_query_id($StaffPMs);
    } //while

    // Close table and multiresolve form
?>
            </table>
<?php     if ($ViewString != 'Resolved' && $IsStaff) { ?>
            <div class="submit_div">
                <input type="submit" value="Resolve selected" />
            </div>
        </form>
<?php
    }
} //if (!$DB->has_results())
?>
    </div>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php

View::show_footer();

?>
