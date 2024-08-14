<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    error(404);
}
if (!$request->canEdit($Viewer)) {
    error(403);
}
$requestId  = $request->id();
$ownRequest = $request->userId() == $Viewer->id();

if (isset($returnEdit)) {
    // if we are coming back from an edit, these were already initialized in take_new_edit
    /** @var string $categoryName */
    /** @var array $artistRole */
    /** @var Gazelle\Request\Encoding $encoding */
    /** @var Gazelle\Request\Format $format */
    /** @var Gazelle\Request\Media $media */
    /** @var Gazelle\Request\LogCue $logCue */
} else {
    $categoryId      = $request->categoryId();
    $categoryName    = $request->categoryName();

    $title           = $request->title();
    $description     = $request->description();
    $year            = $request->year();
    $image           = $request->image();
    $tags            = implode(', ', $request->tagNameList());
    $releaseType     = $request->releaseType();
    $catalogueNumber = $request->catalogueNumber();
    $oclc            = $request->oclc();

    $encoding        = $request->encoding();
    $format          = $request->format();
    $media           = $request->media();
    $logCue          = $request->logCue();
    $artistRole      = $categoryName == 'Music' ? $request->artistRole()->roleNameList() : [];
}

echo $Twig->render('request/request.twig', [
    'action'           => 'edit',
    'error'            => $error ?? null,
    'request'          => $request,
    'category_name'    => $categoryName,
    'artist_role'      => $artistRole,
    'tgroup'           => (new Gazelle\Manager\TGroup())->findById((int)($groupId ?? $request->tgroupId())),
    'release_list'     => (new Gazelle\ReleaseType())->list(),
    'tag_list'         => (new Gazelle\Manager\Tag())->genreList(),
    'catalogue_number' => $catalogueNumber ?? $request->catalogueNumber(),
    'category_id'      => $categoryId      ?? $request->categoryId(),
    'description'      => new Gazelle\Util\Textarea('description', $description ?? $request->description(), 70, 7),
    'encoding'         => $encoding,
    'format'           => $format,
    'log_cue'          => $logCue,
    'media'            => $media,
    'oclc'             => $oclc            ?? $request->oclc(),
    'image'            => $image           ?? $request->image(),
    'record_label'     => $recordLabel     ?? $request->recordLabel(),
    'release_type'     => $releaseType     ?? $request->releaseType(),
    'tags'             => $tags            ?? implode(', ', $request->tagNameList()),
    'title'            => $title           ?? $request->title(),
    'year'             => $year            ?? $request->year(),
    'viewer'           => $Viewer,
]);
