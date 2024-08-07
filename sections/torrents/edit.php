<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

if (($Viewer->id() != $torrent->uploaderId() && !$Viewer->permitted('torrents_edit')) || $Viewer->disableWiki()) {
    error(403);
}

$tgroup       = $torrent->group();
$categoryName = $tgroup->categoryName();
$isMusic      = $categoryName === 'Music';
$artist       = $isMusic ? $tgroup->primaryArtist() : null;

View::show_header('Edit torrent', ['js' => 'upload,torrent']);

if ($Viewer->permitted('torrents_edit') && ($Viewer->permitted('users_mod') || $isMusic)) {
    if ($isMusic) {
?>
<div class="linkbox">
    <a class="brackets" href="#group-change">Change Group</a>
    <a class="brackets" href="#group-split">Split Off into New Group</a>
<?php   if ($Viewer->permitted('users_mod')) { ?>
    <a class="brackets" href="#category-change">Change Category</a>
<?php   } ?>
</div>
<?php
    }
}

if (!($torrent->isRemastered() && !$torrent->remasterYear()) || $Viewer->permitted('edit_unknowns')) {
    $torrentInfo = [
        'ID'                      => $torrent->id(),
        'Media'                   => $torrent->media(),
        'Format'                  => $torrent->format(),
        'Bitrate'                 => $torrent->encoding(),
        'RemasterYear'            => $torrent->remasterYear(),
        'Remastered'              => $torrent->isRemastered(),
        'RemasterTitle'           => $torrent->remasterTitle(),
        'RemasterCatalogueNumber' => $torrent->remasterCatalogueNumber(),
        'RemasterRecordLabel'     => $torrent->remasterRecordLabel(),
        'Scene'                   => $torrent->isScene(),
        'leech_reason'            => $torrent->leechReason(),
        'leech_type'              => $torrent->leechType(),
        'TorrentDescription'      => $torrent->description(),
        'CategoryID'              => $tgroup->categoryId(),
        'Title'                   => $tgroup->name(),
        'Year'                    => $tgroup->year(),
        'VanityHouse'             => $tgroup->isShowcase(),
        'GroupID'                 => $tgroup->id(),
        'UploaderID'              => $torrent->uploaderId(),
        'HasLog'                  => $torrent->hasLog(),
        'HasCue'                  => $torrent->hasCue(),
        'LogScore'                => $torrent->logScore(),
    ];
    foreach (\Gazelle\Enum\TorrentFlag::cases() as $flag) {
        $torrentInfo[$flag->value] = $torrent->hasFlag($flag);
    }
    $uploadForm = new Gazelle\Upload(
        $Viewer,
        $torrentInfo,
        $Err ?? false
    );
    echo $uploadForm->head($tgroup->categoryId());
    echo match ($categoryName) {
        'Audiobooks'        => $uploadForm->audiobook(),
        'Comedy'            => $uploadForm->comedy(),
        'Applications'      => $uploadForm->application(),
        'Comics'            => $uploadForm->comic(),
        'E-Books'           => $uploadForm->ebook(),
        'E-Learning Videos' => $uploadForm->elearning(),
        default => $uploadForm->music([], new Gazelle\Manager\TGroup()),
    };
    echo $uploadForm->foot(false);
};

echo $Twig->render('torrent/edit-torrent.twig', [
    'artist'            => $artist,
    'release_type_list' => (new Gazelle\ReleaseType())->list(),
    'torrent'           => $torrent,
    'viewer'            => $Viewer,
]);
