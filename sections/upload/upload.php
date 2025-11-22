<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Upload form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// $Properties, $Err and $categoryId are set in upload_handle.php,      //
// and are only used when the form doesn't validate and this page must  //
// be called again.                                                     //
//**********************************************************************//

ini_set('max_file_uploads', '100');

$tgMan = new Manager\TGroup();
if (!isset($Properties)) {
    $requestId = (int)($_GET['requestid'] ?? 0);
    if ((int)($_GET['groupid'] ?? 0)) {
        $tgroup = $tgMan->findById((int)$_GET['groupid']);
        if (is_null($tgroup)) {
            unset($_GET['groupid']);
        } else {
            $categoryId = $tgroup->categoryId();
            $Properties = [
                'add-format'       => true,
                'GroupID'          => $tgroup->id(),
                'ReleaseType'      => $tgroup->releaseType(),
                'Title'            => $tgroup->name(),
                'Year'             => $tgroup->year(),
                'Image'            => $tgroup->image(),
                'GroupDescription' => $tgroup->description(),
                'RecordLabel'      => $tgroup->recordLabel(),
                'CatalogueNumber'  => $tgroup->catalogueNumber(),
                'VanityHouse'      => $tgroup->isShowcase(),
                'Artists'          => $tgroup->artistRole()?->idList() ?? [],
                'TagList'          => implode(', ', $tgroup->tagNameList()),
            ];
            if ($requestId) {
                $Properties['RequestID'] = $requestId;
            }
        }
    } elseif ($requestId) {
        $request = new Manager\Request()->findById($requestId);
        if ($request) {
            $categoryId = $request->categoryId();
            $Properties = [
                'add-format'       => true,
                'RequestID'        => $requestId,
                'ReleaseType'      => $request->releaseType(),
                'Title'            => $request->title(),
                'Year'             => $request->year(),
                'Image'            => $request->image(),
                'GroupDescription' => $request->description(),
                'RecordLabel'      => $request->recordLabel(),
                'CatalogueNumber'  => $request->catalogueNumber(),
                'Artists'          => $request->artistRole()?->idList() ?? [],
                'TagList'          => implode(', ', $request->tagNameList()),
            ];
        }
    } elseif (isset($_GET['artistid'])) {
        $artist = new Manager\Artist()->findById((int)$_GET['artistid']);
        if ($artist) {
            $Properties = [
                'add-format' => true,
                'Artists'    => [
                    ARTIST_MAIN => [[
                        'id'   => $artist->id(),
                        'name' => $artist->name(),
                    ]],
                ],
            ];
        }
    }
}

if (!isset($Properties)) {
    $Properties = false;
}
if (!isset($Err)) {
    $Err = false;
}
if (!isset($categoryId)) {
    $categoryId = CATEGORY_MUSIC;
}
$uploadForm = new Upload($Viewer, $Properties, $Err);

View::show_header('Upload', ['js' => 'upload,validate_upload,musicbrainz,bbcode']);
echo $uploadForm->head($categoryId, true);
echo match (CATEGORY[(int)$categoryId - 1]) {
    'Audiobooks',       => $uploadForm->audiobook(),
    'Applications'      => $uploadForm->application(),
    'Comics'            => $uploadForm->comic(),
    'Comedy'            => $uploadForm->comedy(),
    'E-Books'           => $uploadForm->ebook(),
    'E-Learning Videos' => $uploadForm->elearning(),
    default             => $uploadForm->music(new Manager\Tag()->genreList(), $tgMan),
};
echo $uploadForm->foot(true);
