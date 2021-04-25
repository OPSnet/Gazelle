<?php
enforce_login();
if (!empty($_POST['leaderboard'])) {
    $contest = new Gazelle\Contest((int)$_POST['leaderboard']);
}
elseif (!empty($_GET['id'])) {
    $contest = new Gazelle\Contest((int)$_GET['id']);
}
else {
    $contest = $contestMan->currentContest();
}

$title = empty($contest) ? 'Leaderboard' : $contest->name() . ' Leaderboard';
View::show_header($title);

if ($contest->banner()) {
?>
<div class="pad">
    <img border="0" src="<?= $contest->banner() ?>" alt="<?= $contest->name() ?>" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
</div>
<?php } ?>
<div class="linkbox">
    <a href="contest.php" class="brackets">Intro</a>
    <?=(check_perms('admin_manage_contest')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<div class="thin">
<h1><?= $title ?></h1>
<div class="box pad" style="padding: 10px 10px 10px 20px;">

<?php
if (check_perms('admin_manage_contest')) {
    echo $Twig->render('contest/switcher.twig', [
        'current' => $contest->id(),
        'prior'   => $contestMan->priorContests(),
    ]);
}

$paginator = new Gazelle\Util\Paginator(CONTEST_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($contest->totalUsers());
$isRequestFill = $contest instanceof \Gazelle\Contest\RequestFill;
echo $Twig->render('contest/leaderboard.twig', [
    'action'        => $isRequestFill ? 'fill' : 'upload',
    'action_header' => $isRequestFill ? 'requests have been filled' : 'torrents have been uploaded',
    'score_header'  => $isRequestFill ? 'Requests Filled' : 'Perfect FLACs',
    'contest'       => $contest,
    'paginator'     => $paginator,
    'viewer'        => $LoggedUser['ID'],
]);
?>
</div>
<?php
View::show_footer();
