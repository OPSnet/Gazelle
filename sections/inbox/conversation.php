<?php

use Gazelle\Inbox;

$ConvID = (int)$_GET['id'];
if (!$ConvID) {
    error(404);
}

$UserID = $Viewer->id();
[$InInbox, $InSentbox] = $DB->row("
    SELECT InInbox, InSentbox
    FROM pm_conversations_users
    WHERE UserID = ?
        AND ConvID = ?
    ", $UserID, $ConvID
);
if (is_null($InInbox)) {
    error(403);
}

if (!$InInbox && !$InSentbox) {
    error(404);
}

// Get information on the conversation
[$Subject, $Sticky, $UnRead, $ForwardedID] = $DB->row("
    SELECT
        c.Subject,
        cu.Sticky,
        cu.UnRead,
        cu.ForwardedTo
    FROM pm_conversations AS c
    INNER JOIN pm_conversations_users AS cu ON (c.ID = cu.ConvID)
    WHERE cu.UserID = ?
        AND cu.ConvID = ?
    ", $UserID, $ConvID
);

$DB->prepared_query("
    SELECT um.ID, Username
    FROM pm_messages AS pm
    INNER JOIN users_main AS um ON (um.ID = pm.SenderID)
    WHERE pm.ConvID = ?
    ", $ConvID
);
$ConverstionParticipants = $DB->to_array();

$Users = [
    0 => ['UserStr' => 'System', 'Username' => 'System'],
];
foreach ($ConverstionParticipants as $Participant) {
    $PMUserID = (int)$Participant['ID'];
    $Users[$PMUserID] = [
        'UserStr' => Users::format_username($PMUserID, true, true, true, true),
        'Username' => $Participant['Username'],
    ];
}

if ($UnRead == '1') {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            UnRead = '0'
        WHERE UnRead = '1'
            AND UserID = ?
            AND ConvID = ?
        ", $UserID, $ConvID
    );
    // Clear the caches of the inbox and sentbox
    if ($DB->affected_rows() > 0) {
        $Cache->decrement("inbox_new_$UserID");
    }
}

// Get messages
$DB->prepared_query("
    SELECT SentDate, SenderID, Body, ID
    FROM pm_messages
    WHERE ConvID = ?
    ORDER BY ID
    ",$ConvID
);

$Section = (isset($_GET['section']) && in_array($_GET['section'], array_keys(Inbox::SECTIONS)))
    ? $_GET['section']
    : key(Inbox::SECTIONS);
$Sort = (isset($_GET['sort']) && $_GET['sort'] == 'unread') ? Inbox::UNREAD_FIRST : Inbox::NEWEST_FIRST;

View::show_header("View conversation $Subject", ['js' => 'comments,inbox,bbcode,jquery.validate,form_validate']);
?>
<div class="thin">
    <h2><?=$Subject.($ForwardedID > 0 ? " (Forwarded to $ForwardedName)" : '')?></h2>
    <div class="linkbox">
        <a href="<?= Inbox::getLinkQuick($Section, $Sort); ?>" class="brackets">
            Back to <?= $Section ?>
        </a>
    </div>
<?php

while ([$SentDate, $SenderID, $Body, $MessageID] = $DB->next_record()) { ?>
    <div class="box vertical_space">
        <div class="head" style="overflow: hidden;">
            <div style="float: left;">
                <strong><?=$Users[(int)$SenderID]['UserStr']?></strong> <?=time_diff($SentDate)?>
<?php
    if ($SenderID > 0) { ?>
                    - <a href="#quickpost" onclick="Quote('<?=$MessageID?>','<?=$Users[(int)$SenderID]['Username']?>');" class="brackets">Quote</a>
<?php
    } ?>
            </div>
            <div style="float: right;"><a href="#">&uarr;</a> <a href="#messageform">&darr;</a></div>
        </div>
        <div class="body" id="message<?=$MessageID?>">
            <?=Text::full_format($Body)?>
        </div>
    </div>
<?php
}
$DB->prepared_query("
    SELECT UserID
    FROM pm_conversations_users
    WHERE ForwardedTo IN (0, UserID)
        AND UserID != ?
        AND ConvID = ?
    ", $UserID, $ConvID
);
$ReceiverIDs = $DB->collect('UserID');

if (!empty($ReceiverIDs) && (!$Viewer->disablePm() || array_intersect($ReceiverIDs, array_keys($StaffIDs)))) {
?>
    <h3>Reply</h3>
    <form class="send_form" name="reply" action="inbox.php" method="post" id="messageform">
        <div class="box pad">
            <input type="hidden" name="action" value="takecompose" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="toid" value="<?=implode(',', $ReceiverIDs)?>" />
            <input type="hidden" name="convid" value="<?=$ConvID?>" />
            <textarea id="quickpost" class="required" name="body" cols="90" rows="10" onkeyup="resize('quickpost');"></textarea> <br />
            <div id="preview" class="box vertical_space body hidden"></div>
            <div id="buttons" class="center">
                <input type="button" value="Preview" onclick="Quick_Preview();" />
                <input type="submit" value="Send message" />
            </div>
        </div>
    </form>
<?php
}
?>
    <h3>Manage conversation</h3>
    <form class="manage_form" name="messages" action="inbox.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="takeedit" />
            <input type="hidden" name="convid" value="<?=$ConvID?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />

            <table class="layout" width="100%">
                <tr>
                    <td class="label"><label for="sticky">Sticky</label></td>
                    <td>
                        <input type="checkbox" id="sticky" name="sticky"<?php if ($Sticky) { echo ' checked="checked"'; } ?> />
                    </td>
                    <td class="label"><label for="mark_unread">Mark as unread</label></td>
                    <td>
                        <input type="checkbox" id="mark_unread" name="mark_unread" />
                    </td>
                    <td class="label"><label for="delete">Delete conversation</label></td>
                    <td>
                        <input type="checkbox" id="delete" name="delete" />
                    </td>

                </tr>
                <tr>
                    <td class="center" colspan="6"><input type="submit" value="Manage conversation" /></td>
                </tr>
            </table>
        </div>
    </form>
<?php
$FLS = $DB->scalar("
    SELECT SupportFor FROM users_info WHERE UserID = ?
    ", $UserID
);
if ((check_perms('users_mod') || $FLS != '') && (!$ForwardedID || $ForwardedID == $UserID)) {
?>
    <h3>Forward conversation</h3>
    <form class="send_form" name="forward" action="inbox.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="forward" />
            <input type="hidden" name="convid" value="<?=$ConvID?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <label for="receiverid">Forward to</label>
            <select id="receiverid" name="receiverid">
<?php
    foreach ($StaffIDs as $StaffID => $StaffName) {
        if ($StaffID == $Viewer->id() || in_array($StaffID, $ReceiverIDs)) {
            continue;
        }
?>
                <option value="<?=$StaffID?>"><?=$StaffName?></option>
<?php
    }
?>
            </select>
            <input type="submit" value="Forward" />
        </div>
    </form>
<?php
}

//And we're done!
?>
</div>
<?php
View::show_footer();
