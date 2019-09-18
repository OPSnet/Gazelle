<?
class Collages {
    public static function increase_subscriptions($CollageID) {
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
            UPDATE collages
            SET Subscribers = Subscribers + 1
            WHERE ID = '$CollageID'");
        G::$DB->set_query_id($QueryID);
    }

    public static function decrease_subscriptions($CollageID) {
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
            UPDATE collages
            SET Subscribers = IF(Subscribers < 1, 0, Subscribers - 1)
            WHERE ID = '$CollageID'");
        G::$DB->set_query_id($QueryID);
    }

    public static function create_personal_collage() {
        G::$DB->query("
            SELECT
                COUNT(ID)
            FROM collages
            WHERE UserID = '" . G::$LoggedUser['ID'] . "'
                AND CategoryID = '0'
                AND Deleted = '0'");
        list($CollageCount) = G::$DB->next_record();

        if ($CollageCount >= G::$LoggedUser['Permissions']['MaxCollages']) {
            // TODO: fix this, the query was for COUNT(ID), so I highly doubt that this works... - Y
            list($CollageID) = G::$DB->next_record();
            header('Location: collage.php?id='.$CollageID);
            die();
        }
        $NameStr = db_string(G::$LoggedUser['Username'] . "'s personal collage" . ($CollageCount > 0 ? ' no. ' . ($CollageCount + 1) : ''));
        $Description = db_string('Personal collage for ' . G::$LoggedUser['Username'] . '. The first 5 albums will appear on his or her [url=' . site_url() . 'user.php?id= ' . G::$LoggedUser['ID'] . ']profile[/url].');
        G::$DB->query("
            INSERT INTO collages
                (Name, Description, CategoryID, UserID)
            VALUES
                ('$NameStr', '$Description', '0', " . G::$LoggedUser['ID'] . ")");
        $CollageID = G::$DB->inserted_id();
        header('Location: collage.php?id='.$CollageID);
        die();
    }

    public static function collage_cover_row($Group) {
        extract(Torrents::array_group($Group));
        /**
         * @var int    $GroupID
         * @var string $GroupName
         * @var string $GroupYear
         * @var int    $GroupCategoryID
         * @var string $GroupRecordLabel
         * @var array  $Artists
         * @var array  $ExtendedArtists
         * @var string $TagList
         * @var string $WikiImage
         */

        $DisplayName = '';
        if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])|| !empty($ExtendedArtists[6])) {
            unset($ExtendedArtists[2]);
            unset($ExtendedArtists[3]);
            $DisplayName .= Artists::display_artists($ExtendedArtists, false);
        } elseif (count($Artists) > 0) {
            $DisplayName .= Artists::display_artists(array('1' => $Artists), false);
        }
        $DisplayName .= $GroupName;
        if ($GroupYear > 0) {
            $DisplayName = "$DisplayName [$GroupYear]";
        }
        $TorrentTags = new Tags($TagList);
        $Tags = display_str($TorrentTags->format());
        $PlainTags = implode(', ', $TorrentTags->get_tags());
        ob_start();
        ?>
        <li class="image_group_<?=$GroupID?>">
            <a href="torrents.php?id=<?=$GroupID?>" class="bookmark_<?=$GroupID?>">
                <?    if ($WikiImage) { ?>
                    <img class="tooltip_interactive" src="<?=ImageTools::process($WikiImage, true)?>" alt="<?=$DisplayName?>" title="<?=$DisplayName?> <br /> <?=$Tags?>" data-title-plain="<?="$DisplayName ($PlainTags)"?>" width="118" />
                <?    } else { ?>
                    <div style="width: 107px; padding: 5px;"><?=$DisplayName?></div>
                <?    } ?>
            </a>
        </li>
        <?
        return ob_get_clean();
    }
}
