<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

echo $Twig->render('gift/gift.twig', [
    'gift_event' => new Manager\GiftEvent()->findOpenEvent(),
    'viewer'     => $Viewer,
]);
