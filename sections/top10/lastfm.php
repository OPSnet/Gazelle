<?php
define('LIMIT', 100);

$Category = $_GET['category'] ?? 'weekly';
$Category = in_array($Category, ['all_time', 'weekly', 'hyped']) ? $Category : 'weekly';

$View = $_GET['view'] ?? 'tiles';
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

View::show_header('Last.fm', ['js' => 'jquery.imagesloaded,jquery.wookmark,top10', 'css' => 'tiles']);
?>
<div class="thin">
    <div class="header">
        <h2>Last.fm</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'lastfm']) ?>
    </div>
<?= $Twig->render('top10/linkbox-artist.twig', ['category' => $Category, 'view' => $View]) ?>
<?php   if ($View == 'tiles') { ?>
        <div class="tiles_container">
            <ul class="tiles">
<?php
            foreach ($Artists as $artist) {
                if ($artist['name'] == '[unknown]' || !in_array($Category, ['hyped', 'weekly'])) {
                    continue;
                }
                $image = $artist['image'][3]['#text'];
                if (!empty($image)) {
                    $image = image_cache_encode($image);
                    $name  = display_str($artist['name']);
?>
            <li>
                <a href="artist.php?artistname=<?=$name?>">
                    <img class="tooltip large_tile" alt="<?=$name?>" title="<?=$name?>" src="<?= $image ?>" />
                </a>
            </li>
<?php
                }
            }
?>
            </ul>
        </div>
<?php    } else { ?>
        <div class="list_container">
            <ul class="top_artist_list">
<?php
            foreach ($Artists as $artist) {
                if ($artist['name'] == '[unknown]' || !in_array($Category, ['hyped', 'weekly'])) {
                    continue;
                }
                $image = $artist['image'][3]['#text'];
                if (!empty($image)) {
                    $image = image_cache_encode($image);
                    $name  = display_str($artist['name']);
?>
            <li>
                <a class="tooltip_image" data-title-plain="<?= $name
                    ?>" title="&lt;img class=&quot;large_tile&quot; src=&quot;<?=
                    $image ?>&quot; alt=&quot;&quot; /&rsaquo;" href="artist.php?artistname=<?= $name ?>"><?= $name ?></a>
            </li>

<?php
                }
            }
?>
            </ul>
        </div>
<?php   } ?>
    </div>
<?php
View::show_footer();
