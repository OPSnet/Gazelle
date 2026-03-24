<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

echo new Json\Better\SingleSeeded(
    $Viewer,
    new Better\SingleSeeded($Viewer, Enum\BetterFilter::any, new Manager\Torrent())
)
    ->setVersion(2)
    ->response();
