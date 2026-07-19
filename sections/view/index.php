<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['type'], $_GET['id'])) {
    Error400::error();
}

switch ($_GET['type']) {
    case 'riplog':
        if (!preg_match('/^(\d+)\D(\d+)$/', $_GET['id'], $match)) {
            Error404::error();
        }
        if ($match[2] === '0') {
            global $Cache;
            $Cache->add('broken_scraper', 0);
            $Cache->increment('broken_scraper');
            new User\Session($Viewer)->dropAll();
            Error400::error('Your script is broken, fix it.');
        }
        header('Content-type: text/plain');
        header("Content-Disposition: inline; filename=\"{$match[1]}_{$match[2]}.txt\"");
        echo new File\RipLog((int)$match[1], (int)$match[2])->get();
        break;
    default:
        Error404::error();
}
