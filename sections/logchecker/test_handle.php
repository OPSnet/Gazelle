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
    error('No log file uploaded or file is empty.');
}
$logfile = new \Gazelle\Logfile($file['tmp_name'], $file['name']);
if (isset($fileTmp)) {
    unlink($fileTmp);
}

echo $Twig->render('logchecker/test-report.twig', [
    'pasted'  => $isPaste,
    'logfile' => $logfile,
]);
