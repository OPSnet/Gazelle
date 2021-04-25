<?php

/** @var \Gazelle\Bonus $Bonus */

View::show_header('Bonus Points Shop', 'bonus');

if (isset($_GET['complete'])) {
    $label = $_GET['complete'];
    $item = $Bonus->getItem($label);
    print <<<HTML
<div class="alertbar blend">
    {$item['Title']} purchased!
</div>
HTML;
}

?>
<div class="header">
    <h2>Bonus Points Shop</h2>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&amp;name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php?action=bprates" class="brackets">Bonus Point Rates</a>
    <a href="bonus.php?action=history" class="brackets">History</a>
<?php if (check_perms('admin_bp_history')) { ?>
    <a href="bonus.php?action=cacheflush" title="Trigger price recalculation after changing 'bonus-discount' site option" class="brackets">Cache flush</a>
<?php } ?>
</div>

<?php
if (isset($_GET['action']) && $_GET['action'] == 'donate') {
    authorize();
    $value = (int)$_POST['donate'];
    if ($LoggedUser['ID'] != $_POST['userid']) {
?>
<div class="alertbar blend">User error, no bonus points donated.</div>
<?php } elseif ($value <= 0) { ?>
<div class="alertbar blend">Warning! You cannot donate negative or no points!</div>
<?php } elseif ($LoggedUser['BonusPoints'] < $value) { ?>
<div class="alertbar blend">Warning! You cannot donate <?= number_format($value) ?> if you only have <?= number_format((int)$LoggedUser['BonusPoints']) ?> points.</div>
<?php } elseif ($Bonus->donate((int)$_POST['poolid'], $value, $LoggedUser['ID'], $LoggedUser['EffectiveClass'])) { ?>
<div class="alertbar blend">Success! Your donation to the Bonus Point pool has been recorded.</div>
<?php } else { ?>
<div class="alertbar blend">No bonus points donated, insufficient funds.</div>
<?php
    }
}

$user = new Gazelle\User($LoggedUser['ID']);
$points = (int)$LoggedUser['BonusPoints'];
$auth = $LoggedUser['AuthKey'];
$pool = $Bonus->getOpenPool();
if ($pool) {
    echo $Twig->render('bonus/bonus-pool.twig', [
        'auth'    => $auth,
        'points'  => $points,
        'pool'    => $pool,
        'user_id' => $user->id(),
    ]);
}

echo $Twig->render('bonus/store.twig', [
    'admin'    => check_perms('admin_bp_history'),
    'auth'     => $auth,
    'class'    => $user->classLevel(),
    'discount' => $Bonus->discount(),
    'list'     => $Bonus->getListForUser($user),
    'points'   => $points,
    'user_id'  => $user->id(),
]);

View::show_footer();
