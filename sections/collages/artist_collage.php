<?php

$Artists = $Collage->artistList();

$NumGroups = $Collage->numArtists();
$NumGroupsByUser = 0;
$UserAdditions = [];
$Render = [];
$ArtistTable = '';

$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
foreach ($Artists as $id => $Artist) {
    $name = display_str($Artist['name']);
    $image = $Artist['image']
        ? sprintf('<img class="tooltip" src="%s" alt="%s" title="%s" width="118" />',
            $imgProxy->process($Artist['image']), $name, $name)
        : ('<span style="width: 107px; padding: 5px;">' . $name . '</span>');
    $ArtistTable .= "<tr><td><a href=\"artist.php?id=$id\">$name</a></td></tr>";
    $Render[] = "<li class=\"image_group_$id\"><a href=\"artist.php?id=$id\">$image</a></li>";
}

// Pad it out
if ($NumGroups > $CollageCovers) {
    for ($i = $NumGroups + 1; $i <= ceil($NumGroups / $CollageCovers) * $CollageCovers; $i++) {
        $Render[] = '<li></li>';
    }
}

for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) {
    $Groups = array_slice($Render, $i * $CollageCovers, $CollageCovers);
    $CollagePage = '';
    foreach ($Groups as $Group) {
        $CollagePage .= $Group;
    }
    $CollagePages[] = $CollagePage;
}

View::show_header($Collage->name(), ['js' => 'browse,collage,bbcode,voting']);
?>
<div class="thin">
<?= $Twig->render('collage/header.twig', [
    'auth'        => $Viewer->auth(),
    'bookmarked'  => (new Gazelle\Bookmark($Viewer))->isCollageBookmarked($CollageID),
    'can_create'  => $Viewer->permitted('site_collages_create'),
    'can_delete'  => $Viewer->permitted('site_collages_delete') || $Collage->isOwner($Viewer->id()),
    'can_edit'    => $Viewer->permitted('site_collages_delete') || ($Viewer->permitted('site_edit_wiki') && !$Collage->isLocked()),
    'can_manage'  => $Viewer->permitted('site_collages_manage') && !$Collage->isLocked(),
    'can_sub'     => $Viewer->permitted('site_collages_subscribe'),
    'id'          => $CollageID,
    'name'        => $Collage->name(),
    'object'      => 'artist',
    'subbed'      => $Collage->isSubscribed($Viewer->id()),
    'user_id'     => $Viewer->id(),
]);
?>
    <div class="sidebar">
<?= $Twig->render('collage/sidebar.twig', [
    'artists'        => 0, // only makes sense for torrent collages
    'auth'           => $Viewer->auth(),
    'can_add'        => $Viewer->permitted('site_collages_manage') && !$Collage->isLocked(),
    'can_post'       => !$Viewer->disablePosting(),
    'category_id'    => $Collage->categoryId(),
    'category_name'  => COLLAGE[$Collage->categoryId()],
    'comments'       => (new Gazelle\Manager\Comment)->collageSummary($CollageID),
    'contributors'   => array_slice($Collage->contributors(), 0, 5, true),
    'contributors_n' => $Collage->numContributors(),
    'description'    => Text::full_format($Collage->description()),
    'entries'        => $Collage->numArtists(),
    'id'             => $CollageID,
    'object'         => 'artist',
    'object_name'    => 'artist',
    'subscribers'    => $Collage->numSubscribers(),
    'updated'        => $Collage->updated(),
]);
?>
    </div>
    <div class="main_column">
<?php
if ($CollageCovers != 0) {
?>
        <div id="coverart" class="box">
            <div class="head" id="coverhead"><strong>Cover Art</strong></div>
            <ul class="collage_images" id="collage_page0">
<?php
    $Page1 = array_slice($Render, 0, $CollageCovers);
    foreach ($Page1 as $Group) {
        echo $Group;
    }
?>
            </ul>
        </div>
<?php
    if ($NumGroups > $CollageCovers) { ?>
        <div class="linkbox pager" style="clear: left;" id="pageslinksdiv">
            <span id="firstpage" class="invisible"><a href="#" class="pageslink" onclick="collageShow.page(0); return false;"><strong>&laquo; First</strong></a> | </span>
            <span id="prevpage" class="invisible"><a href="#" class="pageslink" onclick="collageShow.prevPage(); return false;"><strong>&lsaquo; Prev</strong></a> | </span>
<?php
        for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) { ?>
            <span id="pagelink<?=$i?>" class="<?=($i > 4 ? 'hidden' : '')?><?=($i == 0 ? 'selected' : '')?>"><a href="#" class="pageslink" onclick="collageShow.page(<?=$i?>, this); return false;"><strong><?=$CollageCovers * $i + 1?>-<?=min($NumGroups, $CollageCovers * ($i + 1))?></strong></a><?=(($i != ceil($NumGroups / $CollageCovers) - 1) ? ' | ' : '')?></span>
<?php   } ?>
            <span id="nextbar" class="<?=($NumGroups / $CollageCovers > 5) ? 'hidden' : ''?>"> | </span>
            <span id="nextpage"><a href="#" class="pageslink" onclick="collageShow.nextPage(); return false;"><strong>Next &rsaquo;</strong></a></span>
            <span id="lastpage" class="<?=(ceil($NumGroups / $CollageCovers) == 2 ? 'invisible' : '')?>"> | <a href="#" class="pageslink" onclick="collageShow.page(<?=ceil($NumGroups / $CollageCovers) - 1?>); return false;"><strong>Last &raquo;</strong></a></span>
        </div>
        <script type="text/javascript">//<![CDATA[
            collageShow.init(<?=json_encode($CollagePages)?>);
        //]]></script>
<?php
    }
}
?>
        <table class="artist_table grouping cats" id="discog_table">
            <tr class="colhead_dark">
                <td><strong>Artists</strong></td>
            </tr>
<?= $ArtistTable ?>
        </table>
    </div>
</div>
