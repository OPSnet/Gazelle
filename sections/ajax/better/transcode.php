<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!in_array((int)($_GET['type'] ?? 0), range(0, 3))) {
    json_error('Unknown transcode type');
}

$search = new Search\Transcode($Viewer);
if (isset($_GET['search'])) {
    $search->setSearch($_GET['search']);
}

if (isset($_GET['filter'])) {
    try {
        $search->setSearch(Enum\BetterFilter::{$_GET['filter']});
    } catch (\Error) {
        json_error('Unknown filter.');
    }
}

if (isset($_GET['target'])) {
    try {
        $search->setEncoding(Enum\BetterEncoding::from($_GET['target']));
    } catch (\ValueError) {
        json_error('Unknown target.');
    }
}

echo new Json\Better\Transcode($Viewer->announceKey(), $search)
    ->setVersion(2)
    ->response();
