<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if ($Viewer->uploadedSize() < 250 * 1024 * 1024 || !$Viewer->permitted('site_submit_requests')) {
    error('You have not enough upload to make a request.');
}

// We may be able to prepare some things based on whence we came
if (isset($_GET['artistid'])) {
    $artist = (new Gazelle\Manager\Artist())->findById((int)$_GET['artistid']);
    if ($artist) {
        $artistRole = [
            ARTIST_MAIN => [$artist->name()],
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
        $artistRole   = $tgroup->artistRole()?->nameList() ?? [];
    }
}

$bounty = $_POST['amount'] ?? $Viewer->ordinal()->value('request-bounty-create');
[$amount, $unit] = array_values(byte_format_array($bounty));
if (in_array($unit, ['GiB', 'TiB'])) {
    $unitGiB = true;
    if ($unit == 'TiB') {
        // the bounty box only knows about MiB and GiB, so if someone
        // uses a value > 1 TiB it needs to be scaled down.
        $bounty *= 1024;
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
    'amount'           => $bounty,
    'amount_box'       => $amount          ?? REQUEST_MIN,
    'unit_GiB'         => isset($unitGiB),
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
