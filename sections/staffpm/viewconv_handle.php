<?php
/** @phpstan-var \Gazelle\User $Viewer */

$manager = new Gazelle\Manager\StaffPM();

$resolve = isset($_POST['resolve']);
$message = trim($_POST['quickpost'] ?? '');

if (empty($message) && !$resolve) {
    error("You must write something in your message");
}

if (isset($_POST['convid'])) {
    $spm = $manager->findById((int)($_POST['convid'] ?? 0));
    if (is_null($spm)) {
        header("Location: staffpm.php");
        exit;
    }
    if (!$spm->visible($Viewer)) {
        error(403);
    }
} elseif (isset($_POST['subject'])) {
    // New staff PM conversation
    if (!isset($_POST['level'])) {
        error("Unclear on the recipient");
    }
    $subject = trim($_POST['subject']);
    if (empty($subject)) {
        error("You must provide a subject for your message");
    }
    $manager->create($Viewer, (int)$_POST['level'], $subject, $message);
    header('Location: staffpm.php');
    exit;
} else {
    error(0);
}

if ($message) {
    $spm->reply($Viewer, $message);
    header("Location: {$spm->location()}");
}
if ($resolve) {
    $spm->resolve($Viewer);
    header("Location: staffpm.php");
}
