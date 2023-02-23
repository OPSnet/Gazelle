<?php

$View = empty($_GET['view']) ? '' : display_str($_GET['view']);

$staffpmMan = new Gazelle\Manager\StaffPM;
$userMan    = new Gazelle\Manager\User;

$viewMap = [
    '' => [
        'status' => ['Unanswered'],
        'count'  => $Viewer->isFLS()
            ? $staffpmMan->countByStatus($Viewer, ['Unanswered'])
            : $staffpmMan->countAtLevel($Viewer, ['Unanswered']),
        'title'  => 'Your Unanswered',
    ],
    'open' => [
        'status' => ['Open'],
        'count'  => $staffpmMan->countByStatus($Viewer, ['Open']),
        'title'  => 'Waiting for reply',
        'view'   => 'Unresolved',
    ],
    'resolved' => [
        'status' => ['Resolved'],
        'title'  => 'Resolved',
        'view'   => 'Resolved',
    ],
];
if ($Viewer->isStaff()) {
    $viewMap = array_merge([
        'unanswered' => [
            'count'  => $staffpmMan->countByStatus($Viewer, ['Unanswered']),
            'status' => ['Unanswered'],
            'title'  => 'All unanswered',
            'view'   => 'Unanswered',
        ]],
        $viewMap
    );
}

if (!isset($viewMap[$View])) {
    error(0);
}
$viewingResolved = $viewMap[$View]['title'] === 'Resolved';

if (isset($_GET['id'])) {
    $cond = ['spc.Level <= ? AND spc.UserID = ? AND spc.status = ?'];
    $args = array_merge([$Viewer->effectiveClass(), (int)$_GET['id'], 'Resolved']);
} else {
    $cond = ['(spc.Level <= ? OR spc.AssignedToUser = ?) AND spc.Status IN (' . placeholders($viewMap[$View]['status']) . ')'];
    $args = array_merge([$Viewer->effectiveClass(), $Viewer->id()], $viewMap[$View]['status']);
}

$Classes = (new Gazelle\Manager\User)->classList();
if ($viewMap[$View]['title'] === 'Your Unanswered') {
    if ($Viewer->effectiveClass() >= $Classes[MOD]['Level']) {
        $cond[] = 'spc.Level >= ?';
        $args[] = $Classes[MOD]['Level'];
    } else if ($Viewer->effectiveClass() == $Classes[FORUM_MOD]['Level']) {
        $cond[] = 'spc.Level >= ?';
        $args[] = $Classes[FORUM_MOD]['Level'];
    }
}
$where = implode(' AND ', $cond);

$db = Gazelle\DB::DB();
$paginator = new Gazelle\Util\Paginator(MESSAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($db->scalar("
    SELECT count(*) FROM staff_pm_conversations AS spc WHERE $where
    ", ...$args
));

array_push($args, $Viewer->id(), $paginator->limit(), $paginator->offset());
$StaffPMs = $db->prepared_query("
    SELECT spc.ID,
        spc.Subject,
        spc.UserID,
        spc.Status,
        spc.Level,
        spc.AssignedToUser,
        spc.Date,
        spc.Unread,
        count(spm.ID) AS NumReplies,
        spc.ResolverID,
        (   SELECT spmu.UserID FROM staff_pm_messages spmu
            WHERE spmu.ID = (
                SELECT max(last.ID) FROM staff_pm_messages last WHERE last.ConvID = spm.ConvID)
        ) as last_user_id
    FROM staff_pm_conversations AS spc
    LEFT JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
    WHERE $where
    GROUP BY spc.ID
    ORDER BY IF(AssignedToUser = ?, 0, 1) ASC, spc.Date DESC
    LIMIT ? OFFSET ?
    ", ...$args
);
$list = $db->to_array(false, MYSQLI_NUM, false);

View::show_header('Staff Inbox');
?>
<div class="thin">
    <div class="header">
        <h2>Staff PMs &rsaquo; <?= $viewMap[$View]['title'] ?></h2>
        <div class="linkbox">
<?php
foreach ($viewMap as $section => $info) {
    if (isset($viewMap[$section]['count'])) {
        $info['title'] .= ' (' . $viewMap[$section]['count'] . ')';
    } elseif ($section === '') {
        $info['title'] .= ' (' . $paginator->total() . ')';
    }
    if ($section === $View) {
        $info['title'] = "<strong>{$info['title']}</strong>";
    }
    // Make sure the trailing space in this output remains.
    ?><a href="staffpm.php<?= empty($section) ? '' : "?view=$section" ?>" class="brackets"><?= $info['title'] ?></a>&nbsp;<?php
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
<?php if (!$db->has_results()) { ?>
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
                    <td>Last reply</td>
<?php    if ($viewingResolved) { ?>
                    <td>Resolved by</td>
<?php    } ?>
                </tr>
<?php
    // List messages
    $ClassLevels = $userMan->classLevelList();
    $Row = 'a';
    foreach ($list as [$ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread, $NumReplies, $ResolverID, $LastUserID]) {
        $Row = $Row === 'a' ? 'b' : 'a';

        // Get assigned
        if ($AssignedToUser != '') {
            $Assigned = $userMan->findByid($AssignedToUser)?->link() ?? 'System';
        } else {
            // Assigned to class
            $Assigned = $Level ? $ClassLevels[$Level]['Name'] : 'First Line Support';
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
                    <td><?= $userMan->findByid($UserID)?->link() ?? 'System' ?></td>
                    <td><?= time_diff($Date, 2, true) ?></td>
                    <td><?= $Assigned ?></td>
                    <td><?= max(0, $NumReplies - 1) ?></td>
                    <td><?= $userMan->findByid($LastUserID)?->link() ?></td>
<?php        if ($viewingResolved) { ?>
                    <td><?= $userMan->findByid($ResolverID)?->link() ?></td>
<?php        } ?>
                </tr>
<?php
        $db->set_query_id($StaffPMs);
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
} // $db->has_results()
?>
    </div>
    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
