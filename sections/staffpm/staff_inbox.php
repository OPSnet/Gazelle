<?php

$View = empty($_GET['view']) ? '' : display_str($_GET['view']);

$staffpmMan = new Gazelle\Manager\StaffPM;
$viewMap = [
    'unanswered' => [
        'view'   => 'Unanswered',
        'status' => ['Unanswered'],
        'count'  => $staffpmMan->countByStatus($Viewer, ['Unanswered']),
    ],
    'open' => [
        'view'    => 'Unresolved',
        'status'  => ['Open', 'Unanswered'],
        'count'   => $staffpmMan->countByStatus($Viewer, ['Open', 'Unanswered']),
        'no_sort' => true,
    ],
    'resolved' => [
        'view'    => 'Resolved',
        'status'  => ['Resolved'],
        'no_sort' => true,
    ],
    'my' => [
        'view'   => 'Your Unanswered',
        'status' => ['Unanswered'],
    ],
    '' => [
        'view'   => $Viewer->isStaff() ? 'Your Unanswered' : 'Unanswered',
        'status' => ['Unanswered'],
    ],
];
if (!isset($viewMap[$View])) {
    error(0);
}
$cond = ['spc.Status IN (' . placeholders($viewMap[$View]['status']) . ') AND (spc.Level <= ? OR spc.AssignedToUser = ?)'];
$args = array_merge($viewMap[$View]['status'], [$Viewer->effectiveClass(), $Viewer->id()]);

$Classes = (new Gazelle\Manager\User)->classList();
if ($viewMap[$View]['view'] === 'Your Unanswered') {
    if ($Viewer->effectiveClass() >= $Classes[MOD]['Level']) {
        $cond[] = 'spc.Level >= ?';
        $args[] = $Classes[MOD]['Level'];
    } else if ($Viewer->effectiveClass() == $Classes[FORUM_MOD]['Level']) {
        $cond[] = 'spc.Level >= ?';
        $args[] = $Classes[FORUM_MOD]['Level'];
    }
}
$where = implode(' AND ', $cond);

$paginator = new Gazelle\Util\Paginator(MESSAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($DB->scalar("
    SELECT count(*)
    FROM staff_pm_conversations AS spc
    LEFT JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
    WHERE $where
    ", ...$args
));

if (isset($viewMap[$View]['no_sort'])) {
    $orderBy = 'spc.Date DESC';
} else {
    $orderBy = 'IF(AssignedToUser = ?, 0, 1) ASC, spc.Date DESC';
    $args[] = $Viewer->id();
}
array_push($args, $paginator->limit(), $paginator->offset());

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
    WHERE $where
    GROUP BY spc.ID
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
    ", ...$args
);

$Sections = [
    'unanswered' => "All unanswered",
    'open'       => "Unresolved",
    'resolved'   => 'Resolved',
];
if ($Viewer->isStaff()) {
    $Sections = ['' => 'Your unanswered'] + $Sections;
}
$viewingResolved = $viewMap[$View]['view'] === 'Resolved';

View::show_header('Staff Inbox');
?>
<div class="thin">
    <div class="header">
        <h2><?= $viewMap[$View]['view'] ?> Staff PMs</h2>
        <div class="linkbox">
<?php
foreach ($Sections as $Section => $Text) {
    if (in_array($Section, ['unanswered', 'open'])) {
        $Text .= '(' . $viewMap[$Section]['count'] . ')';
    }
    if ($Section === $View) {
        $Text = "<strong>$Text</strong>";
    }
    // Make sure the trailing space in this output remains.
    ?><a href="staffpm.php<?= $Section ? "?view=$Section" : '' ?>" class="brackets"><?= $Text ?></a>&nbsp;<?php
}

if ($Viewer->permitted('admin_staffpm_stats')) {
?>
            <a href="staffpm.php?action=scoreboard&amp;view=user" class="brackets">View user scoreboard</a>
            <a href="staffpm.php?action=scoreboard&amp;view=staff" class="brackets">View staff scoreboard</a>
<?php
}
if ($Viewer->isFLS()) { ?>
            <span class="tooltip" title="This is the inbox where replies to Staff PMs you have sent are."><a href="staffpm.php?action=userinbox" class="brackets">Personal Staff Inbox</a></span>
<?php } ?>
        </div>
    </div>
    <br />
    <?= $paginator->linkbox() ?>
    <div class="box pad" id="inbox">
<?php if (!$DB->has_results()) { ?>
        <h2>No messages</h2>
<?php
} else {
    // Messages, draw table
    if (!$viewingResolved && $Viewer->isStaff()) {
        // Open multiresolve form
?>
        <form class="manage_form" name="staff_messages" method="post" action="staffpm.php" id="messageform">
            <input type="hidden" name="action" value="multiresolve" />
            <input type="hidden" name="view" value="<?=strtolower($View)?>" />
<?php } ?>
            <table class="message_table<?=(!$viewingResolved && $Viewer->isStaff()) ? ' checkboxes' : '' ?>">
                <tr class="colhead">
<?php     if (!$viewingResolved && $Viewer->isStaff()) { ?>
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
<?php     } ?>
                    <td>Subject</td>
                    <td>Sender</td>
                    <td>Date</td>
                    <td>Assigned to</td>
                    <td>Replies</td>
<?php    if ($viewingResolved) { ?>
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
<?php         if (!$viewingResolved && $Viewer->isStaff()) { ?>
                    <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
<?php         } ?>
                    <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?= Users::format_username($UserID, true, true, true, true) ?></td>
                    <td><?= time_diff($Date, 2, true) ?></td>
                    <td><?= $Assigned ?></td>
                    <td><?= max(0, $NumReplies - 1) ?></td>
<?php        if ($viewingResolved) { ?>
                    <td><?= Users::format_username($ResolverID, true, true, true, true) ?></td>
<?php        } ?>
                </tr>
<?php
        $DB->set_query_id($StaffPMs);
    } //while
?>
            </table>
<?php     if (!$viewingResolved && $Viewer->isStaff()) { ?>
            <div class="submit_div">
                <input type="submit" value="Resolve selected" />
            </div>
        </form>
<?php
    }
} // $DB->has_results()
?>
    </div>
    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
