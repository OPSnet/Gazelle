<?php

use Gazelle\Util\SortableTableHeader;

if (!isset($_GET['userid'])) {
    header("Location: torrents.php?type={$_GET['type']}&userid={$LoggedUser['ID']}");
    exit;
}

$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
$userId = $user->id();
$viewer = new Gazelle\User($LoggedUser['ID']);

$iconUri = STATIC_SERVER . '/styles/' . $LoggedUser['StyleName'] . '/images';
$imgTag = '<img src="' . $iconUri . '/%s.png" class="tooltip" alt="%s" title="%s"/>';
$headerMap = [
    'name'     => ['dbColumn' => 'tg.Name', 'defaultSort' => 'asc',  'text' => 'Torrent'],
    'time'     => ['dbColumn' => 'Time',    'defaultSort' => 'desc', 'text' => 'Time'],
    'size'     => ['dbColumn' => 't.Size',  'defaultSort' => 'desc', 'text' => 'Size'],
    'snatched' => [
        'dbColumn'      => 'tls.Snatched',
        'defaultSort'   => 'desc',
        'secondarySort' => 't.Size desc',
        'text'          => sprintf($imgTag, 'snatched', 'Snatches', 'Snatches'),
    ],
    'seeders'  => [
        'dbColumn'      => 'tls.Seeders',
        'defaultSort'   => 'desc',
        'secondarySort' => 't.Size desc',
        'text'          => sprintf($imgTag, 'seeders', 'Seeders', 'Seeders'),
    ],
    'leechers' => [
        'dbColumn'      => 'tls.Leechers',
        'defaultSort'   => 'desc',
        'secondarySort' => 't.Size desc',
        'text'          => sprintf($imgTag, 'leechers', 'Leechers', 'Leechers'),
    ],
];
$header = new SortableTableHeader('time', $headerMap);
$secondarySort = $header->current()['secondarySort'] ?? '';
$orderBy = $header->getOrderBy() . ' ' . $header->getOrderDir() . ($secondarySort ? ", $secondarySort" : '');
$headerIcons = new SortableTableHeader('time', $headerMap, ['asc' => '', 'desc' => '']);

if (!empty($_GET['page']) && is_number($_GET['page']) && $_GET['page'] > 0) {
    $page = $_GET['page'];
    $limit = ($page - 1) * TORRENTS_PER_PAGE.', '.TORRENTS_PER_PAGE;
} else {
    $page = 1;
    $limit = TORRENTS_PER_PAGE;
}

$cond = [];
$args = [];
if (!empty($_GET['format'])) {
    if (in_array($_GET['format'], $Formats)) {
        $cond[] = 't.Format = ?';
        $args[] = $_GET['format'];
    } elseif ($_GET['format'] == 'perfectflac') {
        $_GET['filter'] = 'perfectflac';
    }
}

if (!empty($_GET['bitrate']) && in_array($_GET['bitrate'], $Bitrates)) {
    $cond[] = 't.Encoding = ?';
    $args[] = $_GET['bitrate'];
}

if (!empty($_GET['media']) && in_array($_GET['media'], $Media)) {
    $cond[] = 't.Media = ?';
    $args[] = $_GET['media'];
}

$releaseMan = new Gazelle\ReleaseType;
if (!empty($_GET['releasetype'])) {
    $releaseType = (int)$_GET['releasetype'];
    if ($releaseMan->findNameById($releaseType)) {
        $cond[] = 'tg.ReleaseType = ?';
        $args[] = $releaseType;
    }
}

if (isset($_GET['scene']) && in_array($_GET['scene'], ['1', '0'])) {
    $cond[] = 't.Scene = ?';
    $args[] = $_GET['scene'];
}

if (isset($_GET['vanityhouse']) && in_array($_GET['vanityhouse'], ['1', '0'])) {
    $cond[] = 'tg.VanityHouse = ?';
    $args[] = $_GET['vanityhouse'];
}

if (isset($_GET['cue']) && in_array($_GET['cue'], ['1', '0'])) {
    $cond[] = 't.HasCue = ?';
    $args[] = $_GET['cue'];
}

if (isset($_GET['log']) && in_array($_GET['log'], ['1', '0', '100', '-1'])) {
    if ($_GET['log'] === '100') {
        $cond[] = 't.HasLog = ? AND t.LogScore = ?';
        $args[] = '1';
        $args[] = 100;
    } elseif ($_GET['log'] === '-1') {
        $cond[] = 't.HasLog = ? AND t.LogScore < ?';
        $args[] = '1';
        $args[] = 100;
    } else {
        $cond[] = 't.HasLog = ?';
        $args[] = $_GET['log'];
    }
}

if (!empty($_GET['categories'])) {
    $cats = [];
    foreach (array_keys($_GET['categories']) as $cat) {
        if (is_number($cat)) {
            $cats[] = $cat;
        }
    }
    if ($cats) {
        $cond[] = 'tg.CategoryID in (' . placeholders($cats) . ')';
        $args = array_merge($args, $cats);
    }
}

if (!isset($_GET['tags_type'])) {
    $_GET['tags_type'] = '1';
}

if (!empty($_GET['tags'])) {
    $tagMan = new Gazelle\Manager\Tag;
    $tags = explode(',', $_GET['tags']);
    $includeTags = [];
    $excludeTags = [];
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (empty($tag)) {
            continue;
        }
        $negate = $tag[0] === '!';
        $tag = "t.Name = '" . $tagMan->sanitize($tag) . "'";
        if ($negate) {
            $excludeTags[] = $tag;
        } else {
            $includeTags[] = $tag;
        }
    }

    // TODO: placeholderize
    $operator = $_GET['tags_type'] !== '1' ? ' OR ' : ' AND ';
    if (!empty($includeTags)) {
        $cond[] = sprintf('EXISTS (
            SELECT 1
            FROM torrents_tags tt
            INNER JOIN tags t ON (t.ID = tt.TagID)
            WHERE tt.GroupID = tg.ID AND (%s)
        )', implode($operator, $includeTags));
    }
    if (!empty($excludeTags)) {
        $cond[] = sprintf('NOT EXISTS (
            SELECT 1
            FROM torrents_tags tt
            INNER JOIN tags t ON (t.ID = tt.TagID)
            WHERE tt.GroupID = tg.ID AND (%s)
        )', implode($operator, $excludeTags));
    }
}

switch ($_GET['type']) {
    case 'snatched':
        if (!$user->propertyVisible($viewer, 'snatched')) {
            error(403);
        }
        $join = "INNER JOIN xbt_snatched AS xs ON (xs.fid =  t.ID)";
        $time = 'xs.tstamp';
        $userField = 'xs.uid';
        break;
    case 'snatched-unseeded':
        if (!$user->propertyVisible($viewer, 'snatched')) {
            error(403);
        }
        $join = "INNER JOIN xbt_snatched AS xs ON (xs.fid = t.ID)
            LEFT JOIN xbt_files_users AS xfu USING (uid, fid)";
        $cond[] = 'xfu.fid IS NULL';
        $time = 'xs.tstamp';
        $userField = 'xs.uid';
        break;
    case 'seeding':
        if (!$user->propertyVisible($viewer, 'seeding')) {
            error(403);
        }
        $join = "INNER JOIN xbt_files_users AS xfu ON (xfu.fid = t.ID)";
        $cond[] = 'xfu.active = 1 AND xfu.Remaining = 0';
        $time = '(xfu.mtime - xfu.timespent)';
        $userField = 'xfu.uid';
        break;
    case 'leeching':
        if (!$user->propertyVisible($viewer, 'leeching')) {
            error(403);
        }
        $join = "INNER JOIN xbt_files_users AS xfu ON (xfu.fid = t.ID)";
        $cond[] = 'xfu.active = 1 AND xfu.Remaining > 0';
        $time = '(xfu.mtime - xfu.timespent)';
        $userField = 'xfu.uid';
        break;
    case 'uploaded':
        if ((empty($_GET['filter']) || $_GET['filter'] !== 'perfectflac') && !$user->propertyVisible($viewer, 'uploads')) {
            error(403);
        }
        $join = "";
        $time = 'unix_timestamp(t.Time)';
        $userField = 't.UserID';
        break;
    case 'uploaded-unseeded':
        if ((empty($_GET['filter']) || $_GET['filter'] !== 'perfectflac') && !$user->propertyVisible($viewer, 'uploads')) {
            error(403);
        }
        $join = "LEFT JOIN xbt_files_users AS xfu ON (xfu.fid = t.ID AND xfu.uid = t.UserID)";
        $cond[] = 'xfu.fid IS NULL';
        $time = 'unix_timestamp(t.Time)';
        $userField = 't.UserID';
        break;
    case 'downloaded':
        if (!($userId === $viewer->id() || $viewer->permitted('site_view_torrent_snatchlist'))) {
            error(403);
        }
        $join = "INNER JOIN users_downloads AS ud ON (ud.TorrentID = t.ID)";
        $time = 'unix_timestamp(ud.Time)';
        $userField = 'ud.UserID';
        break;
    default:
        error(404);
}

if (!empty($_GET['filter'])) {
    if ($_GET['filter'] === 'perfectflac') {
        if (!$user->propertyVisible($viewer, 'perfectflacs')) {
            error(403);
        }
        $cond[] = "t.Format = ?";
        $args[] = 'FLAC';
        if (empty($_GET['media'])) {
            $cond[] = "(t.LogScore = 100 OR t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT'))";
        } elseif (strtoupper($_GET['media']) === 'CD' && empty($_GET['log'])) {
            $cond[] = "t.LogScore = ?";
            $args[] = 100;
        }
    } elseif ($_GET['filter'] === 'uniquegroup') {
        if (!$user->propertyVisible($viewer, 'uniquegroups')) {
            error(403);
        }
        $groupBy = 'tg.ID';
    }
}

$having = [];
$havingArgs = [];
$havingColumns = '';
$havingCondition = '';
if (trim($_GET['search']) !== '') {
    $join .= "INNER JOIN torrents_artists AS ta ON (ta.GroupID = t.GroupID)
        INNER JOIN artists_alias AS aa ON (aa.AliasID = ta.AliasID)";
    $words = array_unique(array_filter(explode(' ', $_GET['search']), 'mb_strlen'));
    if ($words) {
        $havingColumns = ", aa.Name as aName, tg.Name as gName, tg.Year";
        $having[] = '('
            . implode(' OR ', array_fill(0, count($words),
                "concat_ws(' ', group_concat(aName), gName, tg.Year) LIKE concat('%', ?, '%')"
            ))
            . ')';
        $havingArgs = $words;
    }
}
if ($having) {
    $havingCondition = "HAVING " . implode(' AND ', $having);
}

$cond[] = "$userField = ?";
$args = array_merge($args, [$userId], $havingArgs);

$whereCondition = implode("\nAND ", $cond);
if (empty($groupBy)) {
    $groupBy = 't.ID';
}

$torrentCount = $DB->scalar("
    SELECT count(*) FROM (
        SELECT t.ID $havingColumns
        FROM torrents AS t
        INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        $join
        WHERE $whereCondition
        GROUP BY $groupBy
        $havingCondition
    ) SEARCH
    ", ...$args
);

$DB->prepared_query("
    SELECT
        t.GroupID,
        t.ID AS TorrentID,
        $time AS Time,
        tg.CategoryID $havingColumns
    FROM torrents AS t
    INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
    $join
    WHERE $whereCondition
    GROUP BY $groupBy
    $havingCondition
    ORDER BY $orderBy
    LIMIT $limit
    ", ...$args
);

$groupIDs = $DB->collect('GroupID');
$torrentsInfo = $DB->to_array('TorrentID', MYSQLI_ASSOC);

$results = Torrents::get_groups($groupIDs);
$action = display_str($_GET['type']);
$pages = Format::get_pages($page, $torrentCount, TORRENTS_PER_PAGE);
$urlStem = "torrents.php?userid={$userId}&amp;type=";

View::show_header($user->username() . "'s $action torrents", 'voting');
?>
<div class="thin">
    <div class="linkbox">
    <a class="brackets" href="<?= $urlStem ?>uploaded" title="Torrents you have uploaded">uploaded</a>
    <a class="brackets" href="<?= $urlStem ?>uploaded-unseeded" title="Torrents you have uploaded but are not currently seeding">uploaded (unseeded)</a>
    <a class="brackets" href="<?= $urlStem ?>downloaded" title="Torrents you have downloaded but never snatched completely">downloaded</a>
    <a class="brackets" href="<?= $urlStem ?>snatched" title="Torrents you have snatched">snatched</a>
    <a class="brackets" href="<?= $urlStem ?>snatched-unseeded" title="Torrents you have snatched but are not not currently seeding">snatched (unseeded)</a>
    <a class="brackets" href="<?= $urlStem ?>seeding" title="Your seeding torrents that are earning bonus points">seeding</a>
    <a class="brackets" href="<?= $urlStem ?>leeching" title="Torrents you have downloaded and partially snatched">leeching</a>
    </div>
    <div class="header">
        <h2><a href="user.php?id=<?=$userId?>"><?= $user->username() ?></a>'s <?= str_replace('-', ' and ', $action) ?> torrents</h2>
    </div>
    <div>
        <form class="search_form" name="torrents" action="" method="get">
            <table class="layout">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="hidden" name="type" value="<?=$_GET['type']?>" />
                        <input type="hidden" name="userid" value="<?=$userId?>" />
                        <input type="search" name="search" size="60" value="<?php Format::form('search'); ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Rip specifics:</strong></td>
                    <td class="nobr" colspan="3">
                        <select id="bitrate" name="bitrate" class="ft_bitrate">
                            <option value="">Bitrate</option>
<?php foreach ($Bitrates as $bitrateName) { ?>
                            <option value="<?=display_str($bitrateName); ?>"<?php Format::selected('bitrate', $bitrateName); ?>><?=display_str($bitrateName); ?></option>
<?php } ?>                </select>

                        <select name="format" class="ft_format">
                            <option value="">Format</option>
<?php foreach ($Formats as $formatName) { ?>
                            <option value="<?=display_str($formatName); ?>"<?php Format::selected('format', $formatName); ?>><?=display_str($formatName); ?></option>
<?php } ?>
                            <option value="perfectflac"<?php Format::selected('filter', 'perfectflac'); ?>>Perfect FLACs</option>
                        </select>
                        <select name="media" class="ft_media">
                            <option value="">Media</option>
<?php foreach ($Media as $mediaName) { ?>
                            <option value="<?=display_str($mediaName); ?>"<?php Format::selected('media',$mediaName); ?>><?=display_str($mediaName); ?></option>
<?php } ?>
                        </select>
                        <select name="releasetype" class="ft_releasetype">
                            <option value="">Release type</option>
<?php
    $releaseTypes = $releaseMan->list();
    foreach ($releaseTypes as $id=>$type) {
?>
                            <option value="<?=display_str($id); ?>"<?php Format::selected('releasetype',$id); ?>><?=display_str($type); ?></option>
<?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Misc:</strong></td>
                    <td class="nobr" colspan="3">
                        <select name="log" class="ft_haslog">
                            <option value="">Has log</option>
                            <option value="1"<?php Format::selected('log','1'); ?>>Yes</option>
                            <option value="0"<?php Format::selected('log','0'); ?>>No</option>
                            <option value="100"<?php Format::selected('log','100'); ?>>100% only</option>
                            <option value="-1"<?php Format::selected('log','-1'); ?>>&lt;100%/unscored</option>
                        </select>
                        <select name="cue" class="ft_hascue">
                            <option value="">Has cue</option>
                            <option value="1"<?php Format::selected('cue',1); ?>>Yes</option>
                            <option value="0"<?php Format::selected('cue',0); ?>>No</option>
                        </select>
                        <select name="scene" class="ft_scene">
                            <option value="">Scene</option>
                            <option value="1"<?php Format::selected('scene',1); ?>>Yes</option>
                            <option value="0"<?php Format::selected('scene',0); ?>>No</option>
                        </select>
                        <select name="vanityhouse" class="ft_vanityhouse">
                            <option value="">Vanity House</option>
                            <option value="1"<?php Format::selected('vanityhouse',1); ?>>Yes</option>
                            <option value="0"<?php Format::selected('vanityhouse',0); ?>>No</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Tags:</strong></td>
                    <td>
                        <input type="search" name="tags" size="60" class="tooltip" title="Use !tag to exclude tag" value="<?php Format::form('tags'); ?>" />&nbsp;
                        <input type="radio" name="tags_type" id="tags_type0" value="0"<?php Format::selected('tags_type', 0, 'checked'); ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                        <input type="radio" name="tags_type" id="tags_type1" value="1"<?php Format::selected('tags_type', 1, 'checked'); ?> /><label for="tags_type1"> All</label>
                    </td>
                </tr>

                <tr>
                    <td class="label"><strong>Order by</strong></td>
                    <td>
                        <select name="order" class="ft_order_by">
                            <option value="time"<?php Format::selected('order', 'time'); ?>>Time</option>
                            <option value="name"<?php Format::selected('order', 'name'); ?>>Name</option>
                            <option value="seeders"<?php Format::selected('order', 'seeders'); ?>>Seeders</option>
                            <option value="leechers"<?php Format::selected('order', 'leechers'); ?>>Leechers</option>
                            <option value="snatched"<?php Format::selected('order', 'snatched'); ?>>Snatched</option>
                            <option value="size"<?php Format::selected('order', 'size'); ?>>Size</option>
                        </select>
                        <select name="sort" class="ft_order_way">
                            <option value="desc"<?php Format::selected('sort', 'desc'); ?>>Descending</option>
                            <option value="asc"<?php Format::selected('sort', 'asc'); ?>>Ascending</option>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="layout cat_list">
<?php
$x = 0;
reset($Categories);
foreach ($Categories as $catKey => $catName) {
    if ($x % 7 === 0) {
        if ($x > 0) {
?>
                </tr>
<?php   } ?>
                <tr>
<?php
    }
    $x++;
?>
                    <td>
                        <input type="checkbox" name="categories[<?=($catKey+1)?>]" id="cat_<?=($catKey+1)?>" value="1"<?php if (isset($_GET['categories'][$catKey + 1])) { ?> checked="checked"<?php } ?> />
                        <label for="cat_<?=($catKey + 1)?>"><?=$catName?></label>
                    </td>
<?php } ?>
                </tr>
            </table>
            <div class="submit">
                <input type="submit" value="Search torrents" />
            </div>
        </form>
    </div>
<?php if (!$torrentCount) { ?>
    <div class="center">
        Nothing found!
    </div>
<?php } else { ?>
    <h4><?= number_format($torrentCount) ?> torrent<?= plural($torrentCount) ?> found.</h4>
    <div class="linkbox"><?=$pages?></div>
    <table class="torrent_table cats m_table" width="100%">
        <tr class="colhead">
            <td class="cats_col"></td>
            <td class="m_th_left nobr"><?= $header->emit('name') ?></td>
            <td class="nobr"><?= $header->emit('time') ?></td>
            <td class="nobr"><?= $header->emit('size') ?></td>
            <td class="sign nobr snatches m_th_right"><?= $headerIcons->emit('snatched') ?></td>
            <td class="sign nobr seeders m_th_right"><?= $headerIcons->emit('seeders') ?></td>
            <td class="sign nobr leechers m_th_right"><?= $headerIcons->emit('leechers') ?></td>
        </tr>
<?php
    $pageSize = 0;
    $vote = new \Gazelle\Vote($LoggedUser['ID']);

    foreach ($torrentsInfo as $torrentID => $info) {
        [$groupID, , $time] = array_values($info);

        $groupCategoryID = $results[$groupID]['CategoryID'];
        $groupYear = $results[$groupID]['Year'];
        $groupFlags = isset($results[$groupID]['Flags']) ? $results[$groupID]['Flags'] : ['IsSnatched' => false];
        $torrentTags = new Tags($results[$groupID]['TagList']);
        $torrents = isset($results[$groupID]['Torrents']) ? $results[$groupID]['Torrents'] : [];
        $artists = $results[$groupID]['Artists'];
        $extendedArtists = $results[$groupID]['ExtendedArtists'];
        $torrent = $torrents[$torrentID];

        if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5])) {
            unset($extendedArtists[2]);
            unset($extendedArtists[3]);
            $displayName = Artists::display_artists($extendedArtists);
        } elseif (!empty($artists)) {
            $displayName = Artists::display_artists([1 => $artists]);
        } else {
            $displayName = '';
        }
        $displayName .= '<a href="torrents.php?id='.$groupID.'&amp;torrentid='.$torrentID.'" class="tooltip" title="View torrent" dir="ltr">'.$results[$groupID]['Name'].'</a>';
        if ($groupYear > 0) {
            $displayName .= " [$groupYear]";
        }
        if ($results[$groupID]['VanityHouse']) {
            $displayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }

        $extraInfo = Torrents::torrent_info($torrent);
        if ($extraInfo) {
            $displayName .= " - $extraInfo";
        }
?>
        <tr class="torrent torrent_row<?=($torrent['IsSnatched'] ? ' snatched_torrent' : '') . ($groupFlags['IsSnatched'] ? ' snatched_group' : '')?>">
            <td class="center cats_col">
                <div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($groupCategoryID)?> <?=$torrentTags->css_name()?>"></div>
            </td>
            <td class="td_info big_info">
<?php    if ($LoggedUser['CoverArt']) { ?>
                <div class="group_image float_left clear">
                    <?php ImageTools::cover_thumb($results[$groupID]['WikiImage'], $groupCategoryID) ?>
                </div>
<?php    } ?>
                <div class="group_info clear">
                    <span class="torrent_links_block">
                        [ <a href="torrents.php?action=download&amp;id=<?=$torrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download">DL</a>
                        | <a href="reportsv2.php?action=report&amp;id=<?=$torrentID?>" class="tooltip" title="Report">RP</a> ]
                    </span>
                    <?= $displayName ?>
<?php   if ((!isset($LoggedUser['NoVoteLinks']) || !$LoggedUser['NoVoteLinks']) && check_perms('site_album_votes')) { ?>
                <?= $vote->setGroupId($groupID)->setTwig($Twig)->links($LoggedUser['AuthKey']) ?>
<?php   } ?>
                    <div class="tags"><?=$torrentTags->format('torrents.php?type='.$action.'&amp;userid='.$userId.'&amp;tags=')?></div>
                </div>
            </td>
            <td class="td_time nobr"><?=time_diff($time, 1)?></td>
            <td class="td_size number_column nobr"><?=Format::get_size($torrent['Size'])?></td>
            <td class="td_snatched m_td_right number_column"><?=number_format($torrent['Snatched'])?></td>
            <td class="td_seeders m_td_right number_column<?=(($torrent['Seeders'] == 0) ? ' r00' : '')?>"><?=number_format($torrent['Seeders'])?></td>
            <td class="td_leechers m_td_right number_column"><?=number_format($torrent['Leechers'])?></td>
        </tr>
<?php
    } /* foreach */ ?>
    </table>
<?php
} /* if ($torrentCount) */ ?>
    <div class="linkbox"><?= $pages ?></div>
</div>
<?php
View::show_footer();
