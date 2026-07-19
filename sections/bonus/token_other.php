<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

authorize();

$item = new Manager\Bonus()->findBonusItemByLabel($_REQUEST['label'] ?? '');
if (is_null($item)) {
    Error400::error('Unknown bonus shop item');
}

echo $Twig->render('bonus/token-other.twig', [
    'item'    => $item,
    'message' => new Util\Textarea('message', ''),
    'viewer'  => $Viewer,
]);
