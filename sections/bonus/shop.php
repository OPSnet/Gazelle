<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$bonus    = new User\Bonus($Viewer);
$bonusMan = new Manager\Bonus();

$purchase = $bonusMan->findBonusItemByLabel($_GET['complete'] ?? '');
if (($_GET['action'] ?? '') !== 'donate') {
    $donate = false;
} else {
    authorize();
    $value = (int)$_POST['donate'];
    if ($Viewer->id !== $_POST['userid']) {
        $donate = 'User error, no bonus points donated.';
    } elseif ($value <= 0) {
        $donate = 'Warning! You cannot donate negative or no points!';
    } elseif ($Viewer->bonusPointsTotal() < $value) {
        $donate = 'Warning! You cannot donate ' . number_format($value)
            . ' if you have only ' . number_format($Viewer->bonusPointsTotal(), 0)
            . ' points.';
    } elseif (!isset($_POST['poolid'])) {
        $donate = 'No Bonus Point pool found.';
    } else {
        if ($bonus->donate(new BonusPool((int)$_POST['poolid']), $value)) {
            $donate = 'Success! Your donation to the Bonus Point pool has been recorded.';
        } else {
            $donate = 'No bonus points donated, insufficient funds.';
        }
    }
}

echo $Twig->render('bonus/shop.twig', [
    'bonus'    => $bonus,
    'discount' => $bonusMan->discount(),
    'donate'   => $donate,
    'list'     => $bonusMan->itemList(),
    'pool'     => $bonusMan->openPoolList(),
    'purchase' => $purchase,
    'viewer'   => $Viewer,
]);
