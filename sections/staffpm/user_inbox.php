<?php

$userMan = new \Gazelle\Manager\User;
$classList = $userMan->classList();
View::show_header('Staff PMs', 'staffpm');

// Start page
?>
<div class="thin">
    <div class="header">
        <h2>Staff PMs</h2>
        <div class="linkbox">
<?php if ($Viewer->isFLS()) {?>
            <a href="staffpm.php" class="brackets">Main Staff Inbox</a>
<?php } ?>
            <a href="#" onclick="$('#compose').gtoggle();" class="brackets">Compose new</a>
        </div>
    </div>
    <br />
    <br />
<?= $Twig->render('staffpm/reply.twig', [
    'hidden'=> true,
    'reply' => new Gazelle\Util\Textarea('quickpost', ''),
    'user'  => $Viewer,
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
]); ?>
    <div class="box pad" id="inbox">
<?php

$StaffPMs = $DB->prepared_query("
    SELECT ID,
        Subject,
        UserID,
        Status,
        Level,
        AssignedToUser,
        Date,
        Unread
    FROM staff_pm_conversations
    WHERE UserID = ?
    ORDER BY Status, Date DESC
    ", $Viewer->id()
);

if (!$DB->has_results()) { ?>
        <h2>No messages</h2>
<?php } else { ?>
        <form class="manage_form" name="staff_messages" method="post" action="staffpm.php" id="messageform">
            <input type="hidden" name="action" value="multiresolve" />
            <h3>Open messages</h3>
            <table class="message_table checkboxes">
                <tr class="colhead">
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
                    <td width="50%">Subject</td>
                    <td>Date</td>
                    <td>Assigned to</td>
                </tr>
<?php
    // List messages
    $ClassLevels = $userMan->classLevelList();
    $Row = 'a';
    $ShowBox = 1;
    while ([$ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread] = $DB->next_record()) {
        if ($Unread === '1') {
            $RowClass = 'unreadpm';
        } else {
            $Row = $Row === 'a' ? 'b' : 'a';
            $RowClass = "row$Row";
        }

        if ($Status == 'Resolved') {
            $ShowBox++;
        }
        if ($ShowBox == 2) {
            // First resolved PM
?>
            </table>
            <br />
            <h3>Resolved messages</h3>
            <table class="message_table checkboxes">
                <tr class="colhead">
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform',this)" /></td>
                    <td width="50%">Subject</td>
                    <td>Date</td>
                    <td>Assigned to</td>
                </tr>
<?php
        }

        $Assigned = ($Level == 0) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
        if ($Assigned != 'Sysop') {
            $Assigned .= '+'; // No + on Sysops
        }
?>
                <tr class="<?=$RowClass?>">
                    <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
                    <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?=time_diff($Date, 2, true)?></td>
                    <td><?=$Assigned?></td>
                </tr>
<?php
        $DB->set_query_id($StaffPMs);
    }
?>
            </table>
            <div class="submit_div">
                <input type="submit" value="Resolve selected" />
            </div>
        </form>
<?php } ?>
    </div>
</div>
<?php
View::show_footer();
