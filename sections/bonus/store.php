<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

$bonus = new Gazelle\User\Bonus($Viewer);

View::show_header('Bonus Points Shop', ['js' => 'bonus']);
?>
<div class="header">
    <h2>Bonus Points Shop</h2>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&amp;name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php?action=bprates" class="brackets">Bonus Point Rates</a>
    <a href="bonus.php?action=history" class="brackets">History</a>
<?php if ($Viewer->permitted('admin_bp_history')) { ?>
    <a href="bonus.php?action=cacheflush" title="Trigger price recalculation after changing 'bonus-discount' site option" class="brackets">Cache flush</a>
<?php } ?>
</div>

<?php if (isset($_GET['complete'])) { ?>
<div class="alertbar blend">
    <?= $bonus->item($_GET['complete'])['Title'] ?> purchased!
</div>
<?php
}

if (($_GET['action'] ?? '') == 'donate') {
    authorize();
    $value = (int)$_POST['donate'];
    if ($Viewer->id() != $_POST['userid']) {
?>
<div class="alertbar blend">User error, no bonus points donated.</div>
<?php } elseif ($value <= 0) { ?>
<div class="alertbar blend">Warning! You cannot donate negative or no points!</div>
<?php } elseif ($Viewer->bonusPointsTotal() < $value) { ?>
<div class="alertbar blend">Warning! You cannot donate <?= number_format($value) ?> if you only have <?= number_format((int)$Viewer->bonusPointsTotal()) ?> points.</div>
<?php } elseif ($bonus->donate((int)$_POST['poolid'], $value)) { ?>
<div class="alertbar blend">Success! Your donation to the Bonus Point pool has been recorded.</div>
<?php } else { ?>
<div class="alertbar blend">No bonus points donated, insufficient funds.</div>
<?php
    }
}

$points = (int)$Viewer->bonusPointsTotal();
$bonusMan = new Gazelle\Manager\Bonus();
$auth = $Viewer->auth();
$pool = $bonusMan->getOpenPool();
if ($pool) {
    echo $Twig->render('bonus/bonus-pool.twig', [
        'auth'    => $auth,
        'points'  => $points,
        'pool'    => $pool,
        'user_id' => $Viewer->id(),
    ]);
}

echo $Twig->render('bonus/store.twig', [
    'admin'    => $Viewer->permitted('admin_bp_history'),
    'auth'     => $auth,
    'class'    => $Viewer->classLevel(),
    'discount' => $bonusMan->discount(),
    'list'     => $bonus->itemList(),
    'points'   => $points,
    'user_id'  => $Viewer->id(),
]);
