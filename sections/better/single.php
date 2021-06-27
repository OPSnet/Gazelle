<?php
$better = new Gazelle\Manager\Better(new Gazelle\ReleaseType);
$results = $better->twigGroups($better->singleSeeded());
View::show_header('Single seeder FLACs');

echo $Twig->render('better/single.twig', [
    'results'      => $results,
    'result_count' => count($results),
    'auth_key'     => $Viewer->auth(),
    'torrent_pass' => $Viewer->announceKey(),
    'tokens'       => $Viewer->tokenCount() > 0,
    'torrent_ids'  => implode(',', array_keys($results)),
    'perms'        => [
        'zip_downloader' => check_perms('zip_downloader'),
    ],
]);

View::show_footer();
