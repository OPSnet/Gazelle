<?php

$userMan = new Gazelle\Manager\User;

$search = (new Gazelle\Search\Collage)->setLookup($_GET['type'] ?? 'name');

if (!empty($_GET['bookmarks'])) {
    $search->setBookmarkView($Viewer);
} elseif (!empty($_GET['cats'])) {
    $search->setCategory(array_keys($_GET['cats']));
}

if (($_GET['action'] ?? '') === 'mine') {
    $search->setUser($Viewer)->setPersonal();
} else {
    if (!empty($_GET['search'])) {
        $search->setWordlist($_GET['search']);
    }

    if (!empty($_GET['tags'])) {
        $tagMan = new Gazelle\Manager\Tag;
        $list = explode(',', $_GET['tags']);
        $taglist = [];
        foreach ($list as $name) {
            $name = $tagMan->sanitize($name);
            if (!empty($name)) {
                $taglist[] = $name;
            }
        }
        if ($taglist) {
            $search->setTaglist($taglist)->setTagAll((bool)($_GET['tags_type'] ?? true));
        }
    }

    if (!empty($_GET['userid'])) {
        $user = $userMan->findById((int)$_GET['userid']);
        if (is_null($user)) {
            error(404);
        }
        if (empty($_GET['contrib'])) {
            if (!$user->propertyVisible($Viewer, 'collages')) {
                error(403);
            }
            $search->setUser($user);
        } else {
            if (!$user->propertyVisible($Viewer, 'collagecontribs')) {
                error(403);
            }
            $search->setContributor($user);
        }
    }
}

$paginator = new Gazelle\Util\Paginator(COLLAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$page = $search->page($paginator->limit(), $paginator->offset());

View::show_header($search->isBookmarkView() ? 'Bookmarked collages' : 'Browse collages', ['js' => 'collage']);
?>
<div class="thin">
    <div class="header">
<?php if ($search->isBookmarkView()) { ?>
        <h2><?= $search->userLink() ?> &rsaquo; Bookmarked collages</h2>
<?php
    } else {
        if ($search->userLink()) {
?>
        <h2><?= $search->userLink() ?> &rsaquo; <?= $search->isContributor() ? " Collage contributions" : "Collages" ?></h2>
<?php   } else { ?>
        <h2><?= isset($page) ? " Collage contributions" : "Collages" ?></h2>
<?php
        }
    }
?>
    </div>
<?php if ($search->isBookmarkView()) { ?>
    <div class="linkbox">
        <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
        <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
        <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
        <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
    </div>
<?php } else { ?>
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
                        <input type="radio" name="type" value="name" <?php if ($search->lookup() === 'name') { echo 'checked="checked" '; } ?>/> Names&nbsp;&nbsp;
                        <input type="radio" name="type" value="description" <?php if ($search->lookup() === 'description') { echo 'checked="checked" '; } ?>/> Descriptions
                    </td>
                </tr>
                <tr id="tagfilter">
                    <td class="label">Tags (comma-separated):</td>
                    <td>
                        <input type="text" id="tags" name="tags" size="70" value="<?= empty($_GET['tags']) ? '' : display_str($_GET['tags']) ?>"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> /><br />
                        <input type="radio" name="tags_type" id="tags_type0" value="0"<?= !$search->isTagAll() ? ' checked=checked' : '' ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;

                        <input type="radio" name="tags_type" id="tags_type1" value="1"<?= $search->isTagAll() ? ' checked=checked' : '' ?> /><label for="tags_type1"> All</label>
                    </td>
                </tr>
                <tr id="categories">
                    <td class="label">Categories:</td>
                    <td>
<?php
    $n = 0;
    foreach (COLLAGE as $ID => $Cat) {
?>
                        <span style=" white-space: nowrap;">
                        <input type="checkbox" class="collagecat" value="1" name="cats[<?= $ID ?>]" id="cats_<?= $ID ?>"<?= $search->isSelectedCategory($ID) ? ' checked="checked"' : '' ?> />
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
                            <option value="torrents"<?php Format::selected('order', 'torrents'); ?>>Entries</option>
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
<?php } ?>
    <div class="linkbox">
<?php
    if (!$search->isBookmarkView()) {
        if ($Viewer->permitted('site_collages_create')) {
?>
        <a href="collages.php?action=new" class="brackets">New collage</a>
<?php
        }
        $activeCollages = $Viewer->activePersonalCollages();
        if ($activeCollages === 1) {
            $collages = $Viewer->personalCollages();
?>
        <a href="collages.php?id=<?= $collages[0][0] ?>" class="brackets">Personal collage</a>
<?php       } elseif ($activeCollages > 1) { ?>
        <a href="collages.php?action=mine" class="brackets">Personal collages</a>
<?php
        }
        if ($Viewer->permitted('site_collages_subscribe')) {
?>
        <a href="userhistory.php?action=subscribed_collages" class="brackets">Subscribed collages</a>
<?php   } ?>
        <a href="bookmarks.php?type=collages" class="brackets">Bookmarked collages</a>
        <a href="random.php?action=collage" class="brackets">Random collage</a>
<?php
        if ($Viewer->permitted('site_collages_recover')) {
?>
        <a href="collages.php?action=recover" class="brackets">Recover collage</a>
<?php   } ?>
        <br />
<?php
    }
    if ($Viewer->permittedAny('site_collages_create', 'site_collages_personal')) {
?>
        <a href="collages.php?userid=<?=$Viewer->id()?>" class="brackets">Collages you started</a>
        <a href="collages.php?userid=<?=$Viewer->id()?>&amp;contrib=1" class="brackets">Collages you contributed to</a>
<?php } ?>
    </div>
<?= $paginator->linkbox(); ?>
<?php
    if (count($page) === 0) { ?>
<div class="box pad" align="center">
<?php   if ($search->isBookmarkView()) { ?>
    <h2>You have not bookmarked any collages.</h2>
<?php   } else { ?>
    <h2>Your search did not match anything.</h2>
    <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
<?php   } ?>
</div>
</div>
<?php   View::show_footer();
        die();
    }
    $header = $search->header();
?>
<table width="100%" class="collage_table m_table">
    <tr class="colhead">
        <td class="m_th_left">Category</td>
        <td class="nobr"><?= $header->emit('name') ?> / <?= $header->emit('time') ?></td>
        <td class="m_th_right nobr"><?= $header->emit('torrents') ?></td>
        <td class="m_th_right nobr"><?= $header->emit('subscribers') ?></td>
        <td class="nobr"><?= $header->emit('updated') ?></td>
        <td>Author</td>
    </tr>
<?php
$Row = 'a'; // For the pretty colours
foreach ($page as $Collage) {
    [$ID, $Name, $NumTorrents, $TagList, $CategoryID, $UserID, $Subscribers, $Updated] = $Collage;
    $Row = $Row === 'a' ? 'b' : 'a';
    $TorrentTags = new Tags($TagList);

    //Print results
?>
    <tr class="row<?=$Row?><?= $search->isBookmarkView() ? " bookmark_$ID" : ''; ?>">
        <td class="td_collage_category">
            <a href="collages.php?action=search&amp;cats[<?= (int)$CategoryID ?>]=1"><?= COLLAGE[(int)$CategoryID] ?></a>
        </td>
        <td class="td_info">
            <a href="collages.php?id=<?=$ID?>"><?=$Name?></a>
<?php
    if ($search->isBookmarkView()) { ?>
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
<?= $paginator->linkbox(); ?>
</div>
<?php
View::show_footer();
