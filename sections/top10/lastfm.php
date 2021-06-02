<?php
define('LIMIT', 100);

$Category = isset($_GET['category']) ? $_GET['category'] : 'weekly';
$Category = in_array($Category, ['all_time', 'weekly', 'hyped']) ? $Category : 'weekly';

$View = isset($_GET['view']) ? $_GET['view'] : 'tiles';
$View = in_array($View, ['tiles', 'list']) ? $View : 'tiles';

$lastFM = new Gazelle\Util\LastFM;
switch ($Category) {
    case 'weekly':
        $Artists = json_decode($lastFM->weeklyArtists(LIMIT), true)['artists']['artist'];
        break;
    case 'hyped':
        $Artists = json_decode($lastFM->hypedArtists(LIMIT), true)['artists']['artist'];
        break;
    default:
        break;
}

View::show_header("Last.fm", "jquery.imagesloaded,jquery.wookmark,top10", "tiles");
?>
<div class="thin">
    <div class="header">
        <h2>Last.fm</h2>
<?php   \Gazelle\Top10::renderLinkbox("lastfm"); ?>
    </div>
<?php    \Gazelle\Top10::renderArtistLinks($Category, $View); ?>
<?php    \Gazelle\Top10::renderArtistControls($Category, $View); ?>
<?php   if ($View == 'tiles') { ?>
        <div class="tiles_container">
            <ul class="tiles">
<?php
            foreach ($Artists as $Artist) {
                    \Gazelle\Top10::renderArtistTile($Artist, $Category);
            }
?>
            </ul>
        </div>
<?php    } else { ?>
        <div class="list_container">
            <ul class="top_artist_list">
<?php
            foreach ($Artists as $Artist) {
                    \Gazelle\Top10::renderArtistList($Artist, $Category);
            }
?>
            </ul>
        </div>
<?php   } ?>
    </div>
<?php
View::show_footer();
?>
