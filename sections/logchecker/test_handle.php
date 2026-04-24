<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $isPaste  = false;
    $filename = $_FILES['log']['tmp_name'];
} elseif (!empty($_POST["pastelog"])) {
    $isPaste  = true;
    $filename = tempnam(TMPDIR, 'log_');
    if ($filename === false) {
        // This will only happen if the directory that TMPDIR points to disappears
        Error404::error('Failed to persist the log file.');
    }
    file_put_contents($filename, $_POST["pastelog"]);
} else {
    Error400::error('No log file uploaded or file is empty.');
}

$checker = new Manager\Riplog()->parse($filename);
if ($isPaste) {
    unlink($filename);
}

echo $Twig->render('riplog/test-report.twig', [
    'pasted'  => $isPaste,
    'checker' => $checker,
]);
