<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$label = $_POST['label'] // from a prepare page
    ?? current(
        array_keys(
            array_filter(
                $_POST,
                fn ($i) => $i === 'Purchase!' // from the shop page
            )
        )
    );
if (!is_string($label)) {
    Error400::error("Not sure what you were planning to purchase");
}
$item = new Manager\Bonus()->findBonusItemByLabel($label);
if (is_null($item)) {
    Error404::error("We don't stock that item");
}

$isTitlePurchase = str_starts_with($item->label(), 'title-bb-');
$extra = [];
if ($isTitlePurchase) {
    $extra['title']  = $_POST['title'] ?? '';
} elseif (str_starts_with($item->label(), 'other-')) {
    $user = new Manager\User()->findByUsername($_POST['user'] ?? '');
    if (is_null($user)) {
        Error404::error(
            'Nobody with that name found. Try a user search and give them tokens from their profile page.'
        );
    } elseif ($user->id == $Viewer->id) {
        Error400::error('You cannot gift yourself tokens, they are cheaper to buy directly.');
    }
    $extra['receiver'] = $user;
    $extra['message']  = $_POST['message'] ?? '';
}

// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
switch ($item->purchase($Viewer, $item->price(), $extra)) {
    case Enum\BonusItemPurchaseStatus::success:
        header("Location: bonus.php?complete={$item->label()}");
        exit;

    case Enum\BonusItemPurchaseStatus::insufficientFunds:
        Error400::error("Could not purchase {$item->title()} due to lack of funds.");

    case Enum\BonusItemPurchaseStatus::forbidden:
        Error400::error("You are not allowed to purchase {$item->title()}.");

    case Enum\BonusItemPurchaseStatus::alreadyPurchased:
        Error400::error("You already own {$item->title()}.");

    case Enum\BonusItemPurchaseStatus::incomplete:
        if ($isTitlePurchase) {
            Error400::error("The specified title is too long.");
        } else {
            Error400::error("No recipient specified to gift tokens to.");
        }
}
