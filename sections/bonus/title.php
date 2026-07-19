<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$item = new Manager\Bonus()->findBonusItemByLabel($_REQUEST['label'] ?? '');
if (is_null($item)) {
    Error400::error('Unknown bonus shop item');
}

echo $Twig->render('bonus/title.twig', [
    'item'   => $item,
    'viewer' => $Viewer,
]);
