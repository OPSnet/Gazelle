<?php

if ($Viewer->uploadedSize() < 250 * 1024 * 1024 || !$Viewer->permitted('site_submit_requests')) {
    error('You have not enough upload to make a request.');
}

// We may be able to prepare some things based on whence we came
if (isset($_GET['artistid'])) {
    $artist = (new Gazelle\Manager\Artist())->findById((int)$_GET['artistid']);
    if ($artist) {
        $artistRole = [
            ARTIST_MAIN => [['name' => $artist->name()]],
        ];
    }
} elseif (isset($_GET['groupid'])) {
    $tgroup = (new Gazelle\Manager\TGroup())->findById((int)$_GET['groupid']);
    if ($tgroup) {
        $categoryId   = $tgroup->categoryId();
        $categoryName = $tgroup->categoryName();
        $title        = $tgroup->name();
        $year         = $tgroup->year();
        $releaseType  = $tgroup->releaseType();
        $image        = $tgroup->image();
        $tags         = implode(', ', $tgroup->tagNameList());
        $artistRole   = $tgroup->artistRole()?->idList() ?? [];
    }
}

echo $Twig->render('request/request.twig', [
    'action'           => 'new',
    'category_name'    => $categoryName ?? 'Music',
    'error'            => $error        ?? null,
    'tgroup'           => $tgroup       ?? null,
    'viewer'           => $Viewer,
    'release_list'     => (new Gazelle\ReleaseType())->list(),
    'tag_list'         => (new Gazelle\Manager\Tag())->genreList(),
    'amount'           => $amount          ?? REQUEST_MIN * 1024 ** 2,
    'amount_box'       => $amount          ?? REQUEST_MIN,
    'unit_GiB'         => $unitGiB         ?? false,
    'artist_role'      => $artistRole      ?? [],
    'catalogue_number' => $catalogueNumber ?? '',
    'category_id'      => $categoryId      ?? null,
    'description'      => new Gazelle\Util\Textarea('description', $description ?? '', 70, 7),
    'encoding'         => $encoding        ?? new Gazelle\Request\Encoding(),
    'format'           => $format          ?? new Gazelle\Request\Format(),
    'log_cue'          => $logCue          ?? new Gazelle\Request\LogCue(),
    'media'            => $media           ?? new Gazelle\Request\Media(),
    'oclc'             => $oclc            ?? '',
    'image'            => $image           ?? '',
    'record_label'     => $recordLabel     ?? '',
    'release_type'     => $releaseType     ?? null,
    'tags'             => $tags            ?? '',
    'title'            => $title           ?? '',
    'year'             => $year            ?? '',
]);
