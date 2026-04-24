<?php

declare(strict_types=1);

namespace Gazelle;

if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $filename = $_FILES['log']['tmp_name'];
    $isPaste  = false;
} elseif (isset($_POST["pastelog"])) {
    $filename = tempnam(TMPDIR, 'log_');
    if ($filename === false) {
        // This will only happen if the directory that TMPDIR points to disappears
        json_error('Failed to persist the log file.');
    }
    file_put_contents($filename, $_POST["pastelog"]);
    $isPaste = true;
} else {
    json_error('no log file provided');
}

$checker = new Manager\Riplog()->parse($filename);
if ($isPaste) {
    unlink($filename);
}

json_print('success', [
    'ripper'        => $checker->getRipper(),
    'ripperVersion' => $checker->getRipperVersion(),
    'language'      => $checker->getLanguage(),
    'score'         => $checker->getScore(),
    'checksum'      => $checker->getChecksumState(),
    'issues'        => $checker->getDetails(),
]);
