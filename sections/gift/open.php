<?php

declare(strict_types=1);

namespace Gazelle;

header('Content-Type: application/json');

authorize(ajax: true);

if (!isset($_POST['d'], $_POST['i'], $_POST['s'], $_POST['u'])) {
    json_die('error', 'missing parameters');
}
$giftEvent = new Manager\GiftEvent()->findById((int)$_POST['i']);
if (is_null($giftEvent)) {
    json_die('error', 'event not found');
}

[$status, $gift] = $giftEvent->allocateGift(
    $_POST['d'],
    $_POST['s'],
    (int)$_POST['u'],
);
if ($status === Enum\GiftStatus::success) {
    json_print('success', ['description' => $gift->description()]);
} else {
    json_print('error', ['reason' => $status->value, $_POST]);
}
