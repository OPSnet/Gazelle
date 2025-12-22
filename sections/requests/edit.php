<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$request = new Manager\Request()->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    Error404::error();
}
if (!$request->canEdit($Viewer)) {
    Error403::error("You do not have the necessary privileges to edit requests.");
}
$requestId  = $request->id();
$ownRequest = $request->userId() == $Viewer->id();
// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf
if (isset($returnEdit)) {
// phpcs:enable Generic.CodeAnalysis.EmptyStatement.DetectedIf
    // if we are coming back from an edit, these were already initialized in take_new_edit
    /** @var string $categoryName */
    /** @var array $artistRole */
    /** @var Request\Encoding $encoding */
    /** @var Request\Format $format */
    /** @var Request\Media $media */
    /** @var Request\LogCue $logCue */
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
    'tgroup'           => new Manager\TGroup()->findById((int)($groupId ?? $request->tgroupId())),
    'release_list'     => new ReleaseType()->list(),
    'tag_list'         => new Manager\Tag()->genreList(),
    'catalogue_number' => $catalogueNumber ?? $request->catalogueNumber(),
    'category_id'      => $categoryId      ?? $request->categoryId(),
    'description'      => new Util\Textarea('description', $description ?? $request->description(), 70, 7),
    'encoding'         => $encoding,
    'format'           => $format,
    'log_cue'          => $logCue,
    'media'            => $media,
    'image'            => $image           ?? $request->image(),
    'record_label'     => $recordLabel     ?? $request->recordLabel(),
    'release_type'     => $releaseType     ?? $request->releaseType(),
    'tags'             => $tags            ?? implode(', ', $request->tagNameList()),
    'title'            => $title           ?? $request->title(),
    'year'             => $year            ?? $request->year(),
    'viewer'           => $Viewer,
]);
