<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$request = new Manager\Request()->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    Error404::error();
}
if (!$request->canEdit($Viewer)) {
    Error403::error("You do not have the necessary privileges to edit requests.");
}

$categoryName ??= $request->categoryName();

echo $Twig->render('request/request.twig', [
    'action'           => 'edit',
    'error'            => $error ?? null,
    'request'          => $request,
    'category_name'    => $categoryName,
    'artist_role'      => $categoryName === 'Music' ? ($artistRole ?? $request->artistRole()->roleNameList()) : [],
    'tgroup'           => new Manager\TGroup()->findById((int)($tgroupId ?? $request->tgroupId())),
    'release_list'     => $Viewer->releaseOrderLabel(new ReleaseType()->list()),
    'tag_list'         => new Manager\Tag()->genreList(),
    'catalogue_number' => $catalogueNumber ?? $request->catalogueNumber(),
    'category_id'      => $categoryId      ?? $request->categoryId(),
    'description'      => new Util\Textarea('description', $description ?? $request->description(), 70, 7),
    'encoding'         => $encoding        ?? $request->encoding(),
    'format'           => $format          ?? $request->format(),
    'log_cue'          => $logCue          ?? $request->logCue(),
    'media'            => $media           ?? $request->media(),
    'image'            => $image           ?? $request->image(),
    'record_label'     => $recordLabel     ?? $request->recordLabel(),
    'release_type'     => $releaseType     ?? $request->releaseType(),
    'tags'             => $tags            ?? implode(', ', $request->tagNameList()),
    'title'            => $title           ?? $request->title(),
    'year'             => $year            ?? $request->year(),
    'viewer'           => $Viewer,
]);
