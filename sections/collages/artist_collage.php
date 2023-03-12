<?php

/**
 * @var \Gazelle\Collage $Collage
 * @var int              $CollageCovers
 * @var int              $CollageID
 * @var array            $CollagePages
 * @var int              $NumGroups
 */

$Artists = $Collage->artistList();

$NumGroups = $Collage->numArtists();
$NumGroupsByUser = 0;
$Render = [];
$ArtistTable = '';

$imgProxy = new Gazelle\Util\ImageProxy($Viewer);
foreach ($Artists as $id => $Artist) {
    $name = display_str($Artist['name']);
    $image = $Artist['image']
        ? sprintf('<img class="tooltip" src="%s" alt="%s" title="%s" width="118" />',
            $imgProxy->process($Artist['image']), $name, $name)
        : ('<span style="width: 107px; padding: 5px;">' . $name . '</span>');
    $ArtistTable .= "<tr><td><a href=\"artist.php?id=$id\">$name</a></td></tr>";
    $Render[] = "<li class=\"image_group_$id\"><a href=\"artist.php?id=$id\">$image</a></li>";
}

if ($CollageCovers) {
    if ($NumGroups > $CollageCovers) {
        $Render = array_merge($Render,
            array_fill(0, $CollageCovers * ceil($NumGroups/$CollageCovers) - $NumGroups, '<li></li>')
        );
    }
    for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) {
        $CollagePages[] = implode('', array_slice($Render, $i * $CollageCovers, $CollageCovers));
    }
}

echo $Twig->render('collage/header.twig', [
    'bookmarked' => (new Gazelle\User\Bookmark($Viewer))->isCollageBookmarked($CollageID),
    'collage'    => $Collage,
    'object'     => 'artist',
    'viewer'     => $Viewer,
]);

echo $Twig->render('collage/sidebar.twig', [
    'artists'      => 0, // only makes sense for torrent collages
    'collage'      => $Collage,
    'comments'     => (new Gazelle\Manager\Comment)->collageSummary($CollageID),
    'contributors' => array_slice($Collage->contributors(), 0, 5, true),
    'entries'      => $Collage->numArtists(),
    'object'       => 'artist',
    'object_name'  => 'artist',
    'viewer'       => $Viewer,
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
