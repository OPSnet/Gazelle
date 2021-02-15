<?php

if (!isset($_GET['id'])) {
    header('Location: staffpm.php');
    exit;
}
$ConvID = (int)$_GET['id'];
[$Subject, $UserID, $Level, $AssignedToUser, $Unread, $Status] = $DB->row("
    SELECT Subject, UserID, Level, AssignedToUser, Unread, Status
    FROM staff_pm_conversations
    WHERE ID = ?
    ", $ConvID
);
if (is_null($Subject)) {
    error(404);
}

if (   (!in_array($LoggedUser['ID'], [$UserID, $AssignedToUser]) && !$user->isStaffPMReader())
    || ($user->isFLS() && !in_array($AssignedToUser, ['', $LoggedUser['ID']]))
    || ($user->isStaff() && $Level > $user->effectiveClass())
) {
    // User is trying to view someone else's conversation
    error(403);
}
// User is trying to view their own unread conversation, set it to read
if ($UserID == $LoggedUser['ID'] && $Unread) {
    $DB->prepared_query("
        UPDATE staff_pm_conversations
        SET Unread = false
        WHERE ID = ?
        ", $ConvID
    );
    $Cache->delete_value("staff_pm_new_" . $LoggedUser['ID']);
}

$UserInfo = Users::user_info($UserID);
$UserStr = Users::format_username($UserID, true, true, true, true);
$OwnerID = $UserID;
$OwnerName = $UserInfo['Username'];
View::show_header('Staff PM', 'staffpm,bbcode');
?>
<div class="thin">
    <div class="header">
        <h2>Staff PM - <?=display_str($Subject)?></h2>
        <div class="linkbox">
<?php
$Sections = [];
if ($user->isStaff()) {
    $Sections[''] = 'Your unanswered';
}
if ($user->isStaffPMReader()) {
    $Sections['unanswered'] = "All unanswered";
    $Sections['open']       = "Unresolved";
    $Sections['resolved']   = 'Resolved';
}

foreach ($Sections as $Section => $Text) {
    if ($Section == 'unanswered') {
        $Text .= " (" . $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE (Level <= ? OR AssignedToUser = ?)
                AND Status IN ('Unanswered')
            ", $LoggedUser['EffectiveClass'], $LoggedUser['ID']
        ) . ")";
    }
    if ($Section == 'open') {
        $Text .= " (" . $DB->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE (Level <= ? OR AssignedToUser = ?)
                AND Status IN ('Open', 'Unanswered')
            ", $LoggedUser['EffectiveClass'], $LoggedUser['ID']
        ) . ")";
    }
?><a href="staffpm.php<?= $Section ? "?view=$Section" : '' ?>" class="brackets"><?= $Text ?></a>&nbsp;<?php
}

if (check_perms('admin_staffpm_stats')) { ?>
        <a href="staffpm.php?action=scoreboard&amp;view=user" class="brackets">View user scoreboard</a>
        <a href="staffpm.php?action=scoreboard&amp;view=staff" class="brackets">View staff scoreboard</a>
<?php
}
if ($user->isFLS()) { ?>
        <span class="tooltip"><a href="staffpm.php" class="brackets">Main Staff Inbox</a></span>
        <span class="tooltip" title="This is the inbox where replies to Staff PMs you have sent are."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php
}
if (!$user->isStaffPMReader()) { ?>
        <a href="staffpm.php" class="brackets">Back to inbox</a>
<?php } ?>
    </div>
</div>
<br />
<br />
<div id="inbox">
<?php
// Get messages
$StaffPMs = $DB->prepared_query("
    SELECT UserID, SentDate, Message, ID
    FROM staff_pm_messages
    WHERE ConvID = ?
    ", $ConvID
);
while ([$UserID, $SentDate, $Message, $MessageID] = $DB->next_record()) {
    // Set user string
    if ($UserID == $OwnerID) {
        // User, use prepared string
        $UserString = $UserStr;
        $Username = $OwnerName;
    } else {
        // Staff/FLS
        $UserInfo = Users::user_info($UserID);
        $UserString = Users::format_username($UserID, true, true, true, true);
        $Username = $UserInfo['Username'];
    }
?>
    <div class="box vertical_space" id="post<?=$MessageID?>">
        <div class="head">
<?php       /* TODO: the inline style in the <a> tag is an ugly hack. get rid of it. */ ?>
            <a class="postid" href="staffpm.php?action=viewconv&amp;id=<?=$ConvID?>#post<?=$MessageID?>" style="font-weight: normal;">#<?=$MessageID?></a>
            <strong>
                <?=$UserString?>
            </strong>
            <?=time_diff($SentDate, 2, true)?>
<?php   if ($Status != 'Resolved') { ?>
            - <a href="#quickpost" onclick="Quote('<?=$MessageID?>', '<?=$Username?>');" class="brackets">Quote</a>
<?php   } ?>
        </div>
        <div class="body"><?=Text::full_format($Message)?></div>
    </div>
    <div align="center" style="display: none;"></div>
<?php
    $DB->set_query_id($StaffPMs);
}

// Common responses
if ($user->isStaffPMReader() && $Status != 'Resolved') {
?>
    <div id="common_answers" class="hidden">
        <div class="box vertical_space">
            <div class="head">
                <strong>Preview</strong>
            </div>
            <div id="common_answers_body" class="body">Select an answer from the drop-down to view it.</div>
        </div>
        <br />
        <div class="center">
            <select id="common_answers_select" onchange="UpdateMessage();">
                <option id="first_common_response">Select a message</option>
<?php
    // List common responses
    $DB->prepared_query("
        SELECT ID, Name FROM staff_pm_responses ORDER BY Name ASC
    ");
    while ([$ID, $Name] = $DB->next_record()) {
?>
                <option value="<?=$ID?>"><?=$Name?></option>
<?php   } ?>
            </select>
            <input type="button" title="Use this message as the basis for your reply" value="Use message" onclick="SetMessage();" />
            <input type="button" value="Create new/Edit Common Response" onclick="location.href='staffpm.php?action=responses&amp;convid=<?=$ConvID?>';" />
        </div>
    </div>
<?php
}

// Ajax assign response div
if ($user->isStaffPMReader()) {
?>
    <div id="ajax_message" class="hidden center alertbar"></div>
<?php
}

// Reply box and buttons
?>
    <h3>Reply</h3>
    <div class="box pad" id="reply_box">
        <div id="buttons" class="center">
            <form class="manage_form" name="staff_messages" action="staffpm.php" method="post" id="messageform">
<?php
if ($Status != 'Resolved') {
    $TextPrev = new TEXTAREA_PREVIEW('message', 'quickpost', '', 90, 10, true, false, false, [], true);
}
?>
                <br />
<?php
// Assign to
if ($user->isStaff()) {
    // Staff assign dropdown
?>
                <select id="assign_to" name="assign">
                    <optgroup label="User classes">
<?php        // FLS "class"
    $Selected = ((!$AssignedToUser && $Level == 0) ? ' selected="selected"' : '');
?>
                        <option value="class_0"<?=$Selected?>>First Line Support</option>
<?php        // Staff classes
    $staffLevels = (new Gazelle\Manager\User)->staffLevelList();
    foreach ($staffLevels as $Class) {
        // Create one <option> for each staff user class
        $Selected = ((!$AssignedToUser && ($Level == $Class['Level'])) ? ' selected="selected"' : '');
?>
                        <option value="class_<?=$Class['Level']?>"<?=$Selected?>><?=$Class['Name']?></option>
<?php } ?>
                    </optgroup>
                    <optgroup label="Staff">
<?php // Staff members
    $DB->prepared_query("
        SELECT um.ID,
            um.Username
        FROM users_main AS um 
        INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
        WHERE p.Level >= (SELECT Level FROM permissions WHERE ID = ?)
        ORDER BY p.Level DESC, um.Username ASC
        ", FORUM_MOD
    );
    while ([$ID, $Name] = $DB->next_record()) {
        // Create one <option> for each staff member
        $Selected = (($AssignedToUser == $ID) ? ' selected="selected"' : '');
?>
                        <option value="user_<?=$ID?>"<?=$Selected?>><?=$Name?></option>
<?php   } ?>
                    </optgroup>
                    <optgroup label="First Line Support">
<?php // FLS users
    $DB->prepared_query("
        SELECT um.ID,
            um.Username
        FROM users_main AS um 
        INNER JOIN users_levels ul ON (ul.UserID = um.ID)
        WHERE ul.PermissionID = ?
        ORDER BY um.Username ASC
        ", FLS_TEAM
    );
    while ([$ID, $Name] = $DB->next_record()) {
        // Create one <option> for each FLS user
        $Selected = (($AssignedToUser == $ID) ? ' selected="selected"' : '');
?>
                        <option value="user_<?=$ID?>"<?=$Selected?>><?=$Name?></option>
<?php   } ?>
                    </optgroup>
                </select>
                <input type="button" onclick="Assign();" value="Assign" />
<?php
} elseif ($user->isFLS()) {    /* FLS assign button */ ?>
                <input type="button" value="Assign to staff" onclick="location.href='staffpm.php?action=assign&amp;to=staff&amp;convid=<?=$ConvID?>';" />
                <input type="button" value="Assign to forum staff" onclick="location.href='staffpm.php?action=assign&amp;to=forum&amp;convid=<?=$ConvID?>';" />
<?php
}

if ($Status != 'Resolved') { ?>
                <input type="button" value="Resolve" onclick="location.href='staffpm.php?action=resolve&amp;id=<?=$ConvID?>';" />&nbsp;
<?php   if ($user->isStaffPMReader()) { /* Moved by request */ ?>
                <input type="button" title="Create, edit and use canned replies" value="Common answers" onclick="$('#common_answers').gtoggle();" />
<?php   } ?>
                <input type="button" id="previewbtn" value="Preview" class="hidden button_preview_<?=$TextPrev->getID()?>" />
                <input type="submit" value="Reply" />
<?php } else { ?>
                <input type="button" value="Unresolve" onclick="location.href='staffpm.php?action=unresolve&amp;id=<?=$ConvID?>';" />
<?php } ?>
                <input type="hidden" name="action" value="takepost" />
                <input type="hidden" name="convid" value="<?=$ConvID?>" id="convid" />
            </form>
        </div>
    </div>
</div>
</div>
<?php

View::show_footer();
