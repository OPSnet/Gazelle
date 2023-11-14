<?php

if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $file    = $_FILES['log'];
    $isPaste = false;
} elseif (!empty($_POST["pastelog"])) {
    $fileTmp = tempnam(TMPDIR, 'log_');
    if ($fileTmp === false) {
        // This will only happen if the directory that TMPDIR points to disappears
        error('Failed to persist the log file.');
    }
    file_put_contents($fileTmp, $_POST["pastelog"]);
    $file = [
        'tmp_name' => $fileTmp,
        'name'     => $fileTmp
    ];
    $isPaste = true;
} else {
    json_error('no log file provided');
}

$logfile = new \Gazelle\Logfile($file['tmp_name'], $file['name']);
if (isset($fileTmp)) {
    unlink($fileTmp);
}

json_print('success', [
    'ripper'        => $logfile->ripper(),
    'ripperVersion' => $logfile->ripperVersion(),
    'language'      => $logfile->language(),
    'score'         => $logfile->score(),
    'checksum'      => $logfile->checksumState(),
    'issues'        => $logfile->details(),
]);
