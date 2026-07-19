<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (isset($_POST['label'], $_POST['title'])) {
    authorize(ajax: true);
    echo new Json\BonusItemTitle($_POST['label'], $_POST['title'])->response();
    exit;
}

if (isset($_POST['bonus-user-other'], $_POST['label'])) {
    authorize(ajax: true);
    $item = new Manager\Bonus()->findBonusItemByLabel($_REQUEST['label']);
    if (is_null($item)) {
        json_error('bad label for other token item');
    }
    echo new Json\BonusUserOther($Viewer, $item, $_POST['bonus-user-other'])->response();
    exit;
}

$item = new Manager\Bonus()->findBonusItemByLabel($_REQUEST['item'] ?? '');
if (is_null($item)) {
    Error400::error('Unknown bonus shop item');
}

if (str_starts_with($item->label(), 'title-bb-')) {
    echo $Twig->render('bonus/title.twig', [
        'item'   => $item,
        'viewer' => $Viewer,
    ]);
} elseif (str_starts_with($item->label(), 'other-')) {
    echo $Twig->render('bonus/token-other.twig', [
        'item'    => $item,
        'message' => new Util\Textarea('message', ''),
        'viewer'  => $Viewer,
    ]);
} else {
    Error400::error("Buying this item requires no preparation");
}
