<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$bonus = new Gazelle\User\Bonus($Viewer);
$purchase = isset($_GET['complete']) ? $bonus->item($_GET['complete'])['Title'] : false;
if (($_GET['action'] ?? '') !== 'donate') {
    $donate = false;
} else {
    authorize();
    $value = (int)$_POST['donate'];
    if ($Viewer->id() != $_POST['userid']) {
        $donate = 'User error, no bonus points donated.';
    } elseif ($value <= 0) {
        $donate = 'Warning! You cannot donate negative or no points!';
    } elseif ($Viewer->bonusPointsTotal() < $value) {
        $donate = 'Warning! You cannot donate ' . number_format($value)
            . ' if you have only ' . number_format($Viewer->bonusPointsTotal(), 0)
            . ' points.';
    } elseif ($bonus->donate((int)$_POST['poolid'], $value)) {
        $donate = 'Success! Your donation to the Bonus Point pool has been recorded.';
    } else {
        $donate = 'No bonus points donated, insufficient funds.';
    }
}

$bonusMan = new Gazelle\Manager\Bonus();

echo $Twig->render('bonus/store.twig', [
    'bonus'    => $bonus,
    'discount' => $bonusMan->discount(),
    'donate'   => $donate,
    'pool'     => $bonusMan->getOpenPool(),
    'purchase' => $purchase,
    'viewer'   => $Viewer,
]);
