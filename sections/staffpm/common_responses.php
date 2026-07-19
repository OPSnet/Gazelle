<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->isStaffPMReader()) {
    Error403::error();
}

echo $Twig->render('staffpm/common-response.twig', [
    'conv_id' => $_GET['convid'] ?? false,
    'list'    => new Manager\StaffPM()->commonAnswerList(),
    'new'     => new Util\Textarea("answer-0", '', 87, 10),
    'viewer'  => $Viewer,
]);
