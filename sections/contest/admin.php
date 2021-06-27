<?php

if (!check_perms('admin_manage_contest')) {
    error(403);
}

$create = isset($_GET['action']) && $_GET['action'] === 'create';
$Saved = 0;
if (!empty($_POST['cid'])) {
    authorize();
    $contest = new Gazelle\Contest((int)$_POST['cid']);
    $contest->save($_POST);
    $Saved = 1;
} elseif (!empty($_POST['new'])) {
    authorize();
    $contestMan = new Gazelle\Manager\Contest;
    $contest = $contestMan->create($_POST);
} elseif (!empty($_GET['id'])) {
    $contest = new Gazelle\Contest((int)$_GET['id']);
} elseif (!$create) {
    $contest = $contestMan->currentContest();
}

View::show_header('contest admin');
?>
<div class="thin">
    <div class="header">
        <h2>Contest admin</h2>
        <div class="linkbox">
            <a href="contest.php" class="brackets">Intro</a>
            <a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
<?php if (!$create) { ?>
            <a href="contest.php?action=create" class="brackets">Create</a>
<?php } else { ?>
            <a href="contest.php?action=admin" class="brackets">Admin</a>
<?php } ?>
        </div>
    </div>

<?php
if ($Saved) {
    echo "<p>Contest information saved.</p>";
}
$contestTypes = $contestMan->contestTypes();

if (!$create) {
    echo $Twig->render('contest/list.twig', [
        'current' => !is_null($contest) ? $contest->id() : 0,
        'pointer' => "\xE2\x98\x9E",
        'list' => $contestMan->contestList(),
        'type' => $contestTypes,
    ]);
}

if ($contest instanceof Gazelle\Contest\RequestFill) {
    $userMan = new Gazelle\Manager\User;
?>
    <div class="box pad">
        <h2>Request pairs</h2>
<?php
        $Pairs = $contest->requestPairs();
        if (!count($Pairs)) {
?>
        <p>No members have filled out more than one request for the same member.</p>
<?php   } else { ?>
        <p>The following members have filled out more than one request for the same member.</p>
        <table>
            <tr class="colhead">
                <td>Request filler</td>
                <td>Request creator</td>
                <td>Filled</td>
            </tr>
<?php
            foreach ($Pairs as $p) {
                $filler  = $userMan->findById($p['FillerID']);
                $creator = $userMan->findById($p['UserID']);
?>
            <tr>
                <td><?= $filler->username() ?></td>
                <td><?= $creator->username() ?></td>
                <td><?= $p['nr'] ?></td>
            </tr>
<?php       } ?>
        </table>
<?php   } ?>
    </div>
<?php
} /* request_fill */

if ($create || $contest) {

    echo $Twig->render('contest/admin-form.twig', [
        'action'     => $create ? 'contest.php?action=create' : 'contest.php?action=admin&id=' . $contest->id(),
        'auth'       => $Viewer->auth(),
        'contest'    => $contest,
        'create'     => $create,
        'type'       => $contestTypes,
        'intro'      => new Gazelle\Util\Textarea('description', $create ? '' : $contest->description(), 60, 8),
        'user_count' => (new \Gazelle\Manager\User())->getEnabledUsersCount(),
    ]);
?>
</div>

<?php
} /* !empty($contest) */
View::show_footer();
