<?php
$results = (new Gazelle\Manager\Better(new Gazelle\ReleaseType))->twigGroups($better->singleSeeded());

echo $Twig->render('better/single.twig', [
    'results'      => $results,
    'result_count' => count($results),
    'auth_key'     => $Viewer->auth(),
    'torrent_pass' => $Viewer->announceKey(),
    'tokens'       => $Viewer->tokenCount() > 0,
    'torrent_ids'  => implode(',', array_keys($results)),
    'perms'        => [
        'zip_downloader' => $Viewer->permitted('zip_downloader'),
    ],
]);
