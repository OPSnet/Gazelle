<?php
$FeaturedAlbum = $Cache->get_value('vanity_house_album');
if ($FeaturedAlbum === false) {
    $DB->prepared_query('
        SELECT
            fa.GroupID,
            tg.Name,
            tg.WikiImage,
            fa.ThreadID,
            fa.Title
        FROM featured_albums AS fa
        INNER JOIN torrents_group AS tg ON (tg.ID = fa.GroupID)
        WHERE Ended IS NULL AND type = 1
    ');
    $FeaturedAlbum = $DB->next_record();
    $Cache->cache_value('vanity_house_album', $FeaturedAlbum, 86400 * 3);
}
if (is_number($FeaturedAlbum['GroupID'])) {
    $Artists = Artists::get_artist($FeaturedAlbum['GroupID']);
    $name = Artists::display_artists($Artists, false, false);
?>
        <div class="box">
            <div class="head colhead_dark"><strong>Vanity House</strong> <a href="forums.php?action=viewthread&amp;threadid=<?=$FeaturedAlbum['ThreadID']?>">[Discuss]</a></div>
                        <div class="center pad">
                <a href="torrents.php?id=<?=$FeaturedAlbum['GroupID']?>" class="tooltip" title="<?= $name ?> - <?=$FeaturedAlbum['Name']?>">
                    <img src="<?=ImageTools::process($FeaturedAlbum['WikiImage'], true)?>" alt="<?= $name ?> - <?=$FeaturedAlbum['Name']?>" width="100%" />
                </a>
            </div>
        </div>
<?php
}
