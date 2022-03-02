<?php

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
}

$filterList  = (new Gazelle\User\Notification($Viewer))->filterList(new Gazelle\Manager\User);
$filterList[] = [
    'ID'            => false,
    'Label'         => '',
    'Artists'       => '',
    'ExcludeVA'     => false,
    'NewGroupsOnly' => true,
    'Tags'          => '',
    'NotTags'       => '',
    'ReleaseTypes'  => [],
    'Categories'    => [],
    'Formats'       => [],
    'Encodings'     => [],
    'Media'         => [],
    'FromYear'      => '',
    'ToYear'        => '',
    'Users'         => '',
];

echo $Twig->render('user/edit-notification-filter.twig', [
    'list'         => $filterList,
    'release_type' => (new Gazelle\ReleaseType)->list(),
    'viewer'       => $Viewer,
]);
