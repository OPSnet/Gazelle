<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_torrents_notify')) {
    Error403::error();
}

echo $Twig->render('user/edit-notification-filter.twig', [
    'list' => [
        ...new User\Notification($Viewer)->filterList(new Manager\User()),
        [
            'ID'            => false,
            'Label'         => '',
            'Artists'       => '',
            'ExcludeVA'     => false,
            'NewGroupsOnly' => true,
            'Tags'          => '',
            'NotTags'       => '',
            'RecordLabels'  => [],
            'ReleaseTypes'  => [],
            'Categories'    => [],
            'Formats'       => [],
            'Encodings'     => [],
            'Media'         => [],
            'FromYear'      => '',
            'ToYear'        => '',
            'Users'         => '',
        ]
    ],
    'release_type' => $Viewer->releaseOrderLabel(new ReleaseType()->list()),
    'viewer'       => $Viewer,
]);
