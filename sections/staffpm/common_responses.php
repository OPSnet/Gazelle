<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->isStaffPMReader()) {
    error(403);
}

echo $Twig->render('staffpm/common-response.twig', [
    'conv_id' => $_GET['convid'] ?? false,
    'list'    => (new Gazelle\Manager\StaffPM())->commonAnswerList(),
    'viewer'  => $Viewer,
]);
