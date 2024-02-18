<?php

$manager = new Gazelle\Manager\StaffPM();

if (isset($_POST['convid'])) {
    $staffPM = $manager->findById((int)($_POST['convid'] ?? 0));
    if (is_null($staffPM)) {
        header("Location: staffpm.php");
        exit;
    }
    if (!$staffPM->visible($Viewer)) {
        error(403);
    }
}

if (!isset($_POST['quickpost'])) {
    header('Location: staffpm.php');
    exit;
}

$message = trim($_POST['quickpost']);
if (empty($message)) {
    error("You must write something in your message");
}
if (isset($_POST['subject'])) {
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
}
if (!isset($staffPM)) {
    error(403);
}
$staffPM->reply($Viewer, $message);

header("Location: " . $staffPM->location());
