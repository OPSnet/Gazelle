<?php

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

$pool = $Bonus->getOpenPool();
if ($pool) {
    echo G::$Twig->render('bonus/bonus-pool.twig', [
        'auth'    => $LoggedUser['AuthKey'],
        'points'  => (int)$LoggedUser['BonusPoints'],
        'pool'    => $pool,
        'user_id' => $LoggedUser['ID'],
    ]);
}

echo G::$Twig->render('bonus/store.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'class'   => $LoggedUser['EffectiveClass'],
    'list'    => $Bonus->getListForUser($LoggedUser['ID']),
    'points'  => (int)$LoggedUser['BonusPoints'],
    'user_id' => $LoggedUser['ID'],
]);

View::show_footer();
