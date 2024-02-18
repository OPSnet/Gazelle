<?php

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
}

echo $Twig->render('user/edit-notification-filter.twig', [
    'list' => [
        ...(new Gazelle\User\Notification($Viewer))->filterList(new Gazelle\Manager\User()),
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
    'release_type' => (new Gazelle\ReleaseType())->list(),
    'viewer'       => $Viewer,
]);
