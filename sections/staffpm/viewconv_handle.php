<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$manager = new Manager\StaffPM();

$resolve = isset($_POST['resolve']);
$message = trim($_POST['quickpost'] ?? '');

if (empty($message) && !$resolve) {
    Error400::error("You must write something in your message");
}

if (isset($_POST['convid'])) {
    $spm = $manager->findById((int)($_POST['convid'] ?? 0));
    if (is_null($spm)) {
        header("Location: staffpm.php");
        return;
    }
    if (!$spm->visible($Viewer)) {
        Error403::error();
    }
} elseif (isset($_POST['subject'])) {
    // New staff PM conversation
    if (!isset($_POST['level'])) {
        Error400::error("Unclear on the recipient");
    }
    $subject = trim($_POST['subject']);
    if (empty($subject)) {
        Error400::error("You must provide a subject for your message");
    }
    (void)$manager->create($Viewer, (int)$_POST['level'], $subject, $message);
    header('Location: staffpm.php');
    return;
} else {
    Error400::error();
}

if ($message) {
    $spm->reply($Viewer, $message);
    header("Location: {$spm->location()}");
}
if ($resolve) {
    $spm->resolve($Viewer);
    header("Location: staffpm.php");
}
