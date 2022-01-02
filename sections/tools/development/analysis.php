<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}

$errorLog = new Gazelle\Manager\ErrorLog;
$case = null;

if (isset($_POST['remove'])) {
    $case = $errorLog->findById((int)$_POST['id']);
    if ($case) {
        $case->remove();
    }
} elseif (isset($_POST['prev'])) {
    $case = $errorLog->findByPrev((int)$_POST['id']);
} elseif (isset($_POST['next'])) {
    $case = $errorLog->findByNext((int)$_POST['id']);
} elseif (isset($_POST['remove-prev']) || isset($_POST['remove-next'])) {
    authorize();
    $id = (int)$_POST['id'];
    $case = isset($_POST['remove-prev']) ? $errorLog->findByPrev($id) : $errorLog->findByNext($id);
    $errorLog->findById($id)->remove();
} elseif (isset($_GET['case'])) {
    $case = $errorLog->findById((int)$_GET['case']);
}

if (is_null($case)) {
    header('Location: tools.php?action=analysis_list');
    exit;
}

echo $Twig->render('debug/analysis.twig', [
    'case'   => $case,
    'viewer' => $Viewer,
]);
