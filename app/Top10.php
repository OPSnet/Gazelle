<?php

namespace Gazelle;

class Top10
{
    public static function renderLinkbox(string $selected) {
?>
        <div class="linkbox">
            <a href="top10.php?type=torrents" class="brackets"><?=self::selectedLink("Torrents", $selected == "torrents")?></a>
            <a href="top10.php?type=lastfm" class="brackets"><?=self::selectedLink("Last.fm", $selected == "lastfm")?></a>
            <a href="top10.php?type=users" class="brackets"><?=self::selectedLink("Users", $selected == "users")?></a>
            <a href="top10.php?type=tags" class="brackets"><?=self::selectedLink("Tags", $selected == "tags")?></a>
            <a href="top10.php?type=votes" class="brackets"><?=self::selectedLink("Favorites", $selected == "votes")?></a>
            <a href="top10.php?type=donors" class="brackets"><?=self::selectedLink("Donors", $selected == "donors")?></a>
        </div>
<?php
    }

    public static function renderArtistLinks($selected, $view) {
?>
        <div class="center">
            <a href="top10.php?type=lastfm&amp;category=weekly&amp;view=<?=$view?>" class="brackets tooltip" title="These are the artists with the most Last.fm listeners this week"><?=self::selectedLink("Weekly Artists", $selected == "weekly")?></a>
            <a href="top10.php?type=lastfm&amp;category=hyped&amp;view=<?=$view?>" class="brackets tooltip" title="These are the the fastest rising artists on Last.fm this week"><?=self::selectedLink("Hyped Artists", $selected == "hyped")?></a>

        </div>
<?php
    }

    public static function renderArtistControls($selected, $view) {
?>
        <div class="center">
            <a href="top10.php?type=lastfm&amp;category=<?=$selected?>&amp;view=tiles" class="brackets"><?=self::selectedLink("Tiles", $view == "tiles")?></a>
            <a href="top10.php?type=lastfm&amp;category=<?=$selected?>&amp;view=list" class="brackets"><?=self::selectedLink("List", $view == "list")?></a>
        </div>
<?php
    }

    private static function selectedLink($string, $selected) {
        if ($selected) {
            return "<strong>$string</strong>";
        } else {
            return $string;
        }
    }

    public static function renderArtistTile($artist, $category) {
        if (self::isValidArtist($artist)) {
            switch ($category) {
                case 'weekly':
                case 'hyped':
                    self::renderTile("artist.php?artistname=", $artist['name'], $artist['image'][3]['#text']);
                    break;
                default:
                    break;
            }
        }
    }

    private static function renderTile($url, $name, $image) {
        if (!empty($image)) {
            $name = display_str($name);
            global $Viewer; // FIXME
            $image = (new Util\ImageProxy)->setViewer($Viewer)->process($image);
?>
            <li>
                <a href="<?=$url?><?=$name?>">
                    <img class="tooltip large_tile" alt="<?=$name?>" title="<?=$name?>" src="<?= $image ?>" />
                </a>
            </li>
<?php
        }
    }


    public static function renderArtistList($artist, $category) {
        if (self::isValidArtist($artist)) {
            switch ($category) {

                case 'weekly':
                case 'hyped':
                    self::renderList("artist.php?artistname=", $artist['name'], $artist['image'][3]['#text']);
                    break;
                default:
                    break;
            }
        }
    }

    private static function renderList($url, $name, $image) {
        if (!empty($image)) {
            $image = (new Util\ImageProxy)->process($image);
            $title = "title=\"&lt;img class=&quot;large_tile&quot; src=&quot;$image&quot; alt=&quot;&quot; /&rsaquo;\"";
            $name = display_str($name);
?>
            <li>
                <a class="tooltip_image" data-title-plain="<?=$name?>" <?=$title?> href="<?=$url?><?=$name?>"><?=$name?></a>
            </li>
<?php
        }
    }

    private static function isValidArtist($artist) {
        return $artist['name'] != '[unknown]';
    }

}

