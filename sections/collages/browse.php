<?php

use Gazelle\Util\SortableTableHeader;

define('COLLAGES_PER_PAGE', 25);

list($Page, $Limit) = Format::page_limit(COLLAGES_PER_PAGE);

$SortOrderMap = [
    'time'        => ['ID', 'desc'],
    'name'        => ['c.Name', 'asc'],
    'subscribers' => ['c.Subscribers', 'desc'],
    'torrents'    => ['NumTorrents', 'desc'],
    'updated'     => ['c.Updated', 'desc'],
];
$SortOrder = (!empty($_GET['order']) && isset($SortOrderMap[$_GET['order']])) ? $_GET['order'] : 'time';
$OrderBy = $SortOrderMap[$SortOrder][0];
$OrderWay = (empty($_GET['sort']) || $_GET['sort'] == $SortOrderMap[$SortOrder][1])
    ? $SortOrderMap[$SortOrder][1]
    : SortableTableHeader::SORT_DIRS[$SortOrderMap[$SortOrder][1]];

// Are we searching in bodies, or just names?
if (!empty($_GET['type']) && in_array($_GET['type'], ['c.name', 'description'])) {
    $searchField = $_GET['type'];
} else {
    $searchField = 'c.name';
}

$tagMan = new \Gazelle\Manager\Tag;
if (!empty($_GET['tags'])) {
    $Tags = explode(',', db_string(trim($_GET['tags'])));
    foreach ($Tags as $ID => $Tag) {
        $Tags[$ID] = $tagMan->sanitize($Tag);
    }
}

if (empty($_GET['cats'])) {
    $Categories = array_keys($CollageCats);
} else {
    $Categories = $_GET['cats'];
    foreach ($Categories as $Cat => $Accept) {
        if (empty($CollageCats[$Cat]) || !$Accept) {
            unset($Categories[$Cat]);
        }
    }
    $Categories = array_keys($Categories);
}

$BookmarkView = !empty($_GET['bookmarks']);
if ($BookmarkView) {
    $Categories[] = 0;
    $BookmarkJoin = 'INNER JOIN bookmarks_collages AS bc ON (c.ID = bc.CollageID)';
} else {
    $BookmarkJoin = '';
}

$Args = [];
$SQL = "SELECT
    SQL_CALC_FOUND_ROWS
    c.ID,
    c.Name,
    c.NumTorrents,
    c.TagList,
    c.CategoryID,
    c.UserID,
    c.Subscribers,
    c.Updated
FROM collages AS c
$BookmarkJoin
WHERE Deleted = '0'";

if ($BookmarkView) {
    $SQL .= "\nAND bc.UserID = ?";
    $Args[] = $LoggedUser['ID'];
}

if (isset($_GET['action']) && $_GET['action'] === 'mine') {
    $SQL .= "\nAND c.UserID = ? AND c.CategoryID = 0";
    $Args[] = $LoggedUser['ID'];
} else {
    if (!empty($_GET['search'])) {
        $words = explode(' ', trim($_GET['search']));
        $SQL .= "\nAND " . implode(' AND ', array_fill(0, count($words), "$searchField LIKE concat('%', ?, '%')"));
        $Args = array_merge($Args, $words);
    }

    if (isset($_GET['tags_type']) && $_GET['tags_type'] === '0') { // Any
        $_GET['tags_type'] = '0';
        $ANY = true;
    } else { // All
        $_GET['tags_type'] = '1';
        $ANY = false;
    }

    if (!empty($Tags)) {
        $SQL .= "\nAND ("
            . implode(
                $ANY ? ' OR ' : ' AND ',
                array_fill(0, count($Tags), "TagList LIKE concat('%', ?, '%')")
            )
            . ')';
        $Args = array_merge($Args, $Tags);
    }

    if (!empty($_GET['userid'])) {
        $UserID = $_GET['userid'];
        if (!is_number($UserID)) {
            error(404);
        }
        $User = Users::user_info($UserID);
        $Perms = Permissions::get_permissions($User['PermissionID']);
        $UserClass = $Perms['Class'];

        $UserLink = '<a href="user.php?id='.$UserID.'">'.$User['Username'].'</a>';
        if (!empty($_GET['contrib'])) {
            if (!check_paranoia('collagecontribs', $User['Paranoia'], $UserClass, $UserID)) {
                error(403);
            }
            $DB->prepared_query('
                SELECT DISTINCT CollageID
                FROM collages_torrents
                WHERE UserID = ?
                ', $UserID
            );
            $collageIDs = $DB->collect('CollageID');
            if ($collageIDs) {
                $SQL .= "\nAND c.ID IN (" . placeholders($collageIDs) . ')';
                $Args = array_merge($Args, $collageIDs);
            }
        } else {
            if (!check_paranoia('collages', $User['Paranoia'], $UserClass, $UserID)) {
                error(403);
            }
            $SQL .= "\nAND UserID = ?";
            $Args[] = $_GET['userid'];
        }
        $Categories[] = 0;
    }

    if (!empty($Categories)) {
        $SQL .= "\nAND CategoryID IN (" . placeholders($Categories) . ')';
        $Args = array_merge($Args, $Categories);
    }
}

$SQL .= "\nORDER BY $OrderBy $OrderWay LIMIT $Limit";
$DB->prepared_query($SQL, ...$Args);
$Collages = $DB->to_array();
$DB->query('SELECT FOUND_ROWS()');
list($NumResults) = $DB->next_record();

View::show_header(($BookmarkView) ? 'Your bookmarked collages' : 'Browse collages', 'collage');
?>
<div class="thin">
    <div class="header">
<?php
    if ($BookmarkView) { ?>
        <h2>Your bookmarked collages</h2>
<?php
    } else { ?>
        <h2>Browse collages<?=(!empty($UserLink) ? (isset($CollageIDs) ? " with contributions by $UserLink" : " started by $UserLink") : '')?></h2>
<?php
    } ?>
    </div>
<?php
    if (!$BookmarkView) { ?>
    <div>
        <form class="search_form" name="collages" action="" method="get">
            <div><input type="hidden" name="action" value="search" /></div>
            <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
                <tr id="search_terms">
                    <td class="label">Search for:</td>
                    <td>
                        <input type="search" name="search" size="70" value="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : '')?>" />
                    </td>
                </tr>
                <tr id="search_name_description">
                    <td class="label">Search in:</td>
                    <td>
                        <input type="radio" name="type" value="c.name" <?php if ($searchField === 'c.name') { echo 'checked="checked" '; } ?>/> Names&nbsp;&nbsp;
                        <input type="radio" name="type" value="description" <?php if ($searchField === 'description') { echo 'checked="checked" '; } ?>/> Descriptions
                    </td>
                </tr>
                <tr id="tagfilter">
                    <td class="label">Tags (comma-separated):</td>
                    <td>
                        <input type="text" id="tags" name="tags" size="70" value="<?=(!empty($_GET['tags']) ? display_str($_GET['tags']) : '')?>"<?php Users::has_autocomplete_enabled('other'); ?> /><br />
                        <input type="radio" name="tags_type" id="tags_type0" value="0"<?php Format::selected('tags_type', 0, 'checked'); ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                        <input type="radio" name="tags_type" id="tags_type1" value="1"<?php Format::selected('tags_type', 1, 'checked'); ?> /><label for="tags_type1"> All</label>
                    </td>
                </tr>
                <tr id="categories">
                    <td class="label">Categories:</td>
                    <td>
<?php
    $n = 0;
    foreach ($CollageCats as $ID => $Cat) {
?>
                        <span style=" white-space: nowrap;">
                        <input type="checkbox" class="collagecat" value="1" name="cats[<?= $ID ?>]" id="cats_<?= $ID ?>"<?= in_array($ID, $Categories) ? ' checked="checked"' : '' ?> />
                        <label for="cats_<?= $ID ?>"><?= $Cat ?></label>
                        </span>
<?php
        echo ++$n % 5 ? '&nbsp;&nbsp;&nbsp;' : '<br />';
    }
?>
                        <br />
                        <a href="#" title="Select all collage categories" onclick="javascript:checkCollageCats(true); return false;"><?= ICON_ALL ?></a>
                        <a href="#" title="Clear all collage categories (but at least one will have to be checked)" onclick="javascript:checkCollageCats(false); return false;"><?= ICON_NONE ?></a>
                        <a href="#" title="Toggle the select categories" onclick="javascript:invertCollageCats(); return false;"><?= ICON_TOGGLE ?></a>
                    </td>
                </tr>
                <tr id="order_by">
                    <td class="label">Order by:</td>
                    <td>
                        <select name="order" class="ft_order_by">
                            <option value="time"<?php Format::selected('order', 'time'); ?>>Time</option>
                            <option value="name"<?php Format::selected('order', 'name'); ?>>Name</option>
                            <option value="subscribers"<?php Format::selected('order', 'subscribers'); ?>>Subscribers</option>
                            <option value="torrents"<?php Format::selected('order', 'torrents'); ?>>Torrents</option>
                            <option value="updated"<?php Format::selected('order', 'updated'); ?>>Updated</option>
                        </select>
                        <select name="sort" class="ft_order_way">
                            <option value="desc"<?php Format::selected('sort', 'desc'); ?>>Descending</option>
                            <option value="asc"<?php Format::selected('sort', 'asc'); ?>>Ascending</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" value="Search" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
    } /* if (!$BookmarkView) */ ?>
    <div class="linkbox">
<?php
    if (!$BookmarkView) {
        if (check_perms('site_collages_create')) {
?>
        <a href="collages.php?action=new" class="brackets">New collage</a>
<?php
        }
        if (check_perms('site_collages_personal')) {
            $DB->prepared_query("
                SELECT ID
                FROM collages
                WHERE CategoryID = '0'
                    AND Deleted = '0'
                    AND UserID = ?
                ", $LoggedUser['ID']
            );
            $CollageCount = $DB->record_count();
            if ($CollageCount === 1) {
                list($CollageID) = $DB->next_record();
?>
        <a href="collages.php?id=<?= $CollageID ?>" class="brackets">Personal collage</a>
<?php       } elseif ($CollageCount > 1) { ?>
        <a href="collages.php?action=mine" class="brackets">Personal collages</a>
<?php
            }
        }
        if (check_perms('site_collages_subscribe')) {
?>
        <a href="userhistory.php?action=subscribed_collages" class="brackets">Subscribed collages</a>
<?php   } ?>
        <a href="bookmarks.php?type=collages" class="brackets">Bookmarked collages</a>
<?php   if (check_perms('site_collages_recover')) { ?>
        <a href="collages.php?action=recover" class="brackets">Recover collage</a>
<?php   }
        if (check_perms('site_collages_create') || check_perms('site_collages_personal') || check_perms('site_collages_recover')) {
?>
        <br />
<?php   } ?>
        <a href="collages.php?userid=<?=$LoggedUser['ID']?>" class="brackets">Collages you started</a>
        <a href="collages.php?userid=<?=$LoggedUser['ID']?>&amp;contrib=1" class="brackets">Collages you contributed to</a>
        <a href="random.php?action=collage" class="brackets">Random collage</a>
        <br /><br />
<?php    } else { ?>
        <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
        <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
        <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
        <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
        <br /><br />
<?php
    }
    $Pages = Format::get_pages($Page, $NumResults, COLLAGES_PER_PAGE, 9);
    echo $Pages;
?>
    </div>
<?php
    if (count($Collages) === 0) { ?>
<div class="box pad" align="center">
<?php   if ($BookmarkView) { ?>
    <h2>You have not bookmarked any collages.</h2>
<?php   } else { ?>
    <h2>Your search did not match anything.</h2>
    <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
<?php   } ?>
</div><!--box-->
</div><!--content-->
<?php   View::show_footer();
        die();
    }

$header = new SortableTableHeader([
    'time'        => 'Created',
    'name'        => 'Collage',
    'subscribers' => 'Subscribers',
    'torrents'    => 'Torrents',
    'updated'     => 'Updated',
], $SortOrder, $OrderWay);

?>
<table width="100%" class="collage_table m_table">
    <tr class="colhead">
        <td class="m_th_left">Category</td>
        <td class="nobr"><?= $header->emit('name', $SortOrderMap['name'][1]) ?> / <?= $header->emit('time', $SortOrderMap['time'][1]) ?></td>
        <td class="m_th_right nobr"><?= $header->emit('torrents', $SortOrderMap['torrents'][1]) ?></td>
        <td class="m_th_right nobr"><?= $header->emit('subscribers', $SortOrderMap['subscribers'][1]) ?></td>
        <td class="nobr"><?= $header->emit('updated', $SortOrderMap['updated'][1]) ?></td>
        <td>Author</td>
    </tr>
<?php
$Row = 'a'; // For the pretty colours
foreach ($Collages as $Collage) {
    list($ID, $Name, $NumTorrents, $TagList, $CategoryID, $UserID, $Subscribers, $Updated) = $Collage;
    $Row = $Row === 'a' ? 'b' : 'a';
    $TorrentTags = new Tags($TagList);

    //Print results
?>
    <tr class="row<?=$Row?><?= $BookmarkView ? " bookmark_$ID" : ''; ?>">
        <td class="td_collage_category">
            <a href="collages.php?action=search&amp;cats[<?= (int)$CategoryID ?>]=1"><?= $CollageCats[(int)$CategoryID] ?></a>
        </td>
        <td class="td_info">
            <a href="collages.php?id=<?=$ID?>"><?=$Name?></a>
<?php
    if ($BookmarkView) { ?>
            <span style="float: right;">
                <a href="#" onclick="Unbookmark('collage', <?=$ID?>, ''); return false;" class="brackets">Remove bookmark</a>
            </span>
<?php
    } ?>
            <div class="tags"><?=$TorrentTags->format('collages.php?action=search&amp;tags=')?></div>
        </td>
        <td class="td_torrent_count m_td_right number_column"><?=number_format((int)$NumTorrents)?></td>
        <td class="td_subscribers m_td_right number_column"><?=number_format((int)$Subscribers)?></td>
        <td class="td_updated nobr"><?=time_diff($Updated)?></td>
        <td class="td_author"><?=Users::format_username($UserID, false, false, false)?></td>
    </tr>
<?php
}
?>
</table>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
View::show_footer();
