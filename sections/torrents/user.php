<?php


$Orders = array('Time', 'Name', 'Seeders', 'Leechers', 'Snatched', 'Size');
$Ways = array('DESC' => 'Descending', 'ASC' => 'Ascending');
$UserVotes = Votes::get_user_votes($LoggedUser['ID']);

// The "order by x" links on columns headers
function header_link($SortKey, $DefaultWay = 'DESC') {
    global $Order, $Way;
    if ($SortKey == $Order) {
        if ($Way == 'DESC') {
            $NewWay = 'ASC';
        } else {
            $NewWay = 'DESC';
        }
    } else {
        $NewWay = $DefaultWay;
    }

    return "torrents.php?way=$NewWay&amp;order=$SortKey&amp;" . Format::get_url(array('way','order'));
}

if (!isset($_GET['userid'])) {
    header("Location: torrents.php?type={$_GET['type']}&userid={$LoggedUser['ID']}");
    die();
}

$UserID = $_GET['userid'];
if (!is_number($UserID)) {
    error(0);
}

if (!empty($_GET['page']) && is_number($_GET['page']) && $_GET['page'] > 0) {
    $Page = $_GET['page'];
    $Limit = ($Page - 1) * TORRENTS_PER_PAGE.', '.TORRENTS_PER_PAGE;
} else {
    $Page = 1;
    $Limit = TORRENTS_PER_PAGE;
}

if (!empty($_GET['order']) && in_array($_GET['order'], $Orders)) {
    $Order = $_GET['order'];
} else {
    $Order = 'Time';
}

if (!empty($_GET['way']) && array_key_exists($_GET['way'], $Ways)) {
    $Way = $_GET['way'];
} else {
    $Way = 'DESC';
}

$SearchWhere = [];
if (!empty($_GET['format'])) {
    if (in_array($_GET['format'], $Formats)) {
        $SearchWhere[] = "t.Format = '".db_string($_GET['format'])."'";
    } elseif ($_GET['format'] == 'perfectflac') {
        $_GET['filter'] = 'perfectflac';
    }
}

if (!empty($_GET['bitrate']) && in_array($_GET['bitrate'], $Bitrates)) {
    $SearchWhere[] = "t.Encoding = '".db_string($_GET['bitrate'])."'";
}

if (!empty($_GET['media']) && in_array($_GET['media'], $Media)) {
    $SearchWhere[] = "t.Media = '".db_string($_GET['media'])."'";
}

if (!empty($_GET['releasetype']) && array_key_exists($_GET['releasetype'], $ReleaseTypes)) {
    $SearchWhere[] = "tg.ReleaseType = '".db_string($_GET['releasetype'])."'";
}

if (isset($_GET['scene']) && in_array($_GET['scene'], array('1', '0'))) {
    $SearchWhere[] = "t.Scene = '".db_string($_GET['scene'])."'";
}

if (isset($_GET['vanityhouse']) && in_array($_GET['vanityhouse'], array('1', '0'))) {
    $SearchWhere[] = "tg.VanityHouse = '".db_string($_GET['vanityhouse'])."'";
}

if (isset($_GET['cue']) && in_array($_GET['cue'], array('1', '0'))) {
    $SearchWhere[] = "t.HasCue = '".db_string($_GET['cue'])."'";
}

if (isset($_GET['log']) && in_array($_GET['log'], array('1', '0', '100', '-1'))) {
    if ($_GET['log'] === '100') {
        $SearchWhere[] = "t.HasLog = '1'";
        $SearchWhere[] = "t.LogScore = '100'";
    } elseif ($_GET['log'] === '-1') {
        $SearchWhere[] = "t.HasLog = '1'";
        $SearchWhere[] = "t.LogScore < '100'";
    } else {
        $SearchWhere[] = "t.HasLog = '".db_string($_GET['log'])."'";
    }
}

if (!empty($_GET['categories'])) {
    $Cats = [];
    foreach (array_keys($_GET['categories']) as $Cat) {
        if (!is_number($Cat)) {
            error(0);
        }
        $Cats[] = "tg.CategoryID = '".db_string($Cat)."'";
    }
    $SearchWhere[] = '('.implode(' OR ', $Cats).')';
}

if (!isset($_GET['tags_type'])) {
    $_GET['tags_type'] = '1';
}

if (!empty($_GET['tags'])) {
    $Tags = explode(',', $_GET['tags']);
    $TagList = [];
    foreach ($Tags as $Tag) {
        $Tag = trim(str_replace('.', '_', $Tag));
        if (empty($Tag)) {
            continue;
        }
        if ($Tag[0] == '!') {
            $Tag = ltrim(substr($Tag, 1));
            if (empty($Tag)) {
                continue;
            }
            $TagList[] = "CONCAT(' ', tg.TagList, ' ') NOT LIKE '% ".db_string($Tag)." %'";
        } else {
            $TagList[] = "CONCAT(' ', tg.TagList, ' ') LIKE '% ".db_string($Tag)." %'";
        }
    }
    if (!empty($TagList)) {
        if (isset($_GET['tags_type']) && $_GET['tags_type'] !== '1') {
            $_GET['tags_type'] = '0';
            $SearchWhere[] = '('.implode(' OR ', $TagList).')';
        } else {
            $_GET['tags_type'] = '1';
            $SearchWhere[] = '('.implode(' AND ', $TagList).')';
        }
    }
}

$SearchWhere = implode(' AND ', $SearchWhere);
if (!empty($SearchWhere)) {
    $SearchWhere = " AND $SearchWhere";
}

$User = Users::user_info($UserID);
$Perms = Permissions::get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];

switch ($_GET['type']) {
    case 'snatched':
        if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = 'xs.tstamp';
        $UserField = 'xs.uid';
        $ExtraWhere = '';
        $From = "
            xbt_snatched AS xs
                INNER JOIN torrents AS t ON t.ID = xs.fid
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";
        break;
    case 'seeding':
        if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = '(xfu.mtime - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = '
            AND xfu.active = 1
            AND xfu.Remaining = 0';
        $From = "
            xbt_files_users AS xfu
                INNER JOIN torrents AS t ON t.ID = xfu.fid
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";
        break;
    case 'leeching':
        if (!check_paranoia('leeching', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = '(xfu.mtime - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = '
            AND xfu.active = 1
            AND xfu.Remaining > 0';
        $From = "
            xbt_files_users AS xfu
                INNER JOIN torrents AS t ON t.ID = xfu.fid
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";
        break;
    case 'uploaded':
        if ((empty($_GET['filter']) || $_GET['filter'] !== 'perfectflac') && !check_paranoia('uploads', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = 'unix_timestamp(t.Time)';
        $UserField = 't.UserID';
        $ExtraWhere = '';
        $From = "torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";
        break;
    case 'downloaded':
        if (!check_perms('site_view_torrent_snatchlist')) {
            error(403);
        }
        $Time = 'unix_timestamp(ud.Time)';
        $UserField = 'ud.UserID';
        $ExtraWhere = '';
        $From = "users_downloads AS ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";
        break;
    default:
        error(404);
}

if (!empty($_GET['filter'])) {
    if ($_GET['filter'] === 'perfectflac') {
        if (!check_paranoia('perfectflacs', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $ExtraWhere .= " AND t.Format = 'FLAC'";
        if (empty($_GET['media'])) {
            $ExtraWhere .= "
                AND (
                    t.LogScore = 100 OR
                    t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT')
                    )";
        } elseif (strtoupper($_GET['media']) === 'CD' && empty($_GET['log'])) {
            $ExtraWhere .= "
                AND t.LogScore = 100";
        }
    } elseif ($_GET['filter'] === 'uniquegroup') {
        if (!check_paranoia('uniquegroups', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $GroupBy = 'tg.ID';
    }
}

if (empty($GroupBy)) {
    $GroupBy = 't.ID';
}

if ((empty($_GET['search']) || trim($_GET['search']) === '') && $Order != 'Name') {
    $QueryOrder = $Order;
    if (in_array($Order, ['Seeders', 'Leechers', 'Snatched'])) {
        $QueryOrder = "tls.$QueryOrder";
    }
    $SQL = "
        SELECT
            SQL_CALC_FOUND_ROWS
            t.GroupID,
            t.ID AS TorrentID,
            $Time AS Time,
            tg.CategoryID
        FROM $From
            JOIN torrents_group AS tg ON tg.ID = t.GroupID
        WHERE $UserField = '$UserID'
            $ExtraWhere
            $SearchWhere
        GROUP BY $GroupBy
        ORDER BY $QueryOrder $Way
        LIMIT $Limit";
} else {
    $DB->query("
        CREATE TEMPORARY TABLE temp_sections_torrents_user (
            GroupID int(10) unsigned not null,
            TorrentID int(10) unsigned not null,
            Time int(12) unsigned not null,
            CategoryID int(3) unsigned,
            Seeders int(6) unsigned,
            Leechers int(6) unsigned,
            Snatched int(10) unsigned,
            Name mediumtext,
            Size bigint(12) unsigned,
        PRIMARY KEY (TorrentID)) CHARSET=utf8");
    $DB->query("
        INSERT IGNORE INTO temp_sections_torrents_user
            SELECT
                t.GroupID,
                t.ID AS TorrentID,
                $Time AS Time,
                tg.CategoryID,
                tls.Seeders,
                tls.Leechers,
                tls.Snatched,
                CONCAT_WS(' ', GROUP_CONCAT(aa.Name SEPARATOR ' '), ' ', tg.Name, ' ', tg.Year, ' ') AS Name,
                t.Size
            FROM $From
                INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
                LEFT JOIN torrents_artists AS ta ON ta.GroupID = tg.ID
                LEFT JOIN artists_alias AS aa ON aa.AliasID = ta.AliasID
            WHERE $UserField = '$UserID'
                $ExtraWhere
                $SearchWhere
            GROUP BY TorrentID, Time");

    if (!empty($_GET['search']) && trim($_GET['search']) !== '') {
        $Words = array_unique(explode(' ', db_string($_GET['search'])));
    }

    $SQL = "
        SELECT
            SQL_CALC_FOUND_ROWS
            GroupID,
            TorrentID,
            Time,
            CategoryID
        FROM temp_sections_torrents_user";
    if (!empty($Words)) {
        $SQL .= "
        WHERE Name LIKE '%".implode("%' AND Name LIKE '%", $Words)."%'";
    }
    $SQL .= "
        ORDER BY $Order $Way
        LIMIT $Limit";
}

$DB->query($SQL);
$GroupIDs = $DB->collect('GroupID');
$TorrentsInfo = $DB->to_array('TorrentID', MYSQLI_ASSOC);

$DB->query('SELECT FOUND_ROWS()');
list($TorrentCount) = $DB->next_record();

$Results = Torrents::get_groups($GroupIDs);

$Action = display_str($_GET['type']);
$User = Users::user_info($UserID);

View::show_header($User['Username']."'s $Action torrents",'voting');

$Pages = Format::get_pages($Page, $TorrentCount, TORRENTS_PER_PAGE);


?>
<div class="thin">
    <div class="header">
        <h2><a href="user.php?id=<?=$UserID?>"><?=$User['Username']?></a><?="'s $Action torrents"?></h2>
    </div>
    <div>
        <form class="search_form" name="torrents" action="" method="get">
            <table class="layout">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="hidden" name="type" value="<?=$_GET['type']?>" />
                        <input type="hidden" name="userid" value="<?=$UserID?>" />
                        <input type="search" name="search" size="60" value="<?php Format::form('search'); ?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Rip specifics:</strong></td>
                    <td class="nobr" colspan="3">
                        <select id="bitrate" name="bitrate" class="ft_bitrate">
                            <option value="">Bitrate</option>
<?php    foreach ($Bitrates as $BitrateName) { ?>
                            <option value="<?=display_str($BitrateName); ?>"<?php Format::selected('bitrate', $BitrateName); ?>><?=display_str($BitrateName); ?></option>
<?php    } ?>                </select>

                        <select name="format" class="ft_format">
                            <option value="">Format</option>
<?php    foreach ($Formats as $FormatName) { ?>
                            <option value="<?=display_str($FormatName); ?>"<?php Format::selected('format', $FormatName); ?>><?=display_str($FormatName); ?></option>
<?php    } ?>
                            <option value="perfectflac"<?php Format::selected('filter', 'perfectflac'); ?>>Perfect FLACs</option>
                        </select>
                        <select name="media" class="ft_media">
                            <option value="">Media</option>
<?php    foreach ($Media as $MediaName) { ?>
                            <option value="<?=display_str($MediaName); ?>"<?php Format::selected('media',$MediaName); ?>><?=display_str($MediaName); ?></option>
<?php    } ?>
                        </select>
                        <select name="releasetype" class="ft_releasetype">
                            <option value="">Release type</option>
<?php    foreach ($ReleaseTypes as $ID=>$Type) { ?>
                            <option value="<?=display_str($ID); ?>"<?php Format::selected('releasetype',$ID); ?>><?=display_str($Type); ?></option>
<?php    } ?>
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
<?php    foreach ($Orders as $OrderText) { ?>
                            <option value="<?=$OrderText?>"<?php Format::selected('order', $OrderText); ?>><?=$OrderText?></option>
<?php    } ?>
                        </select>&nbsp;
                        <select name="way" class="ft_order_way">
<?php    foreach ($Ways as $WayKey=>$WayText) { ?>
                            <option value="<?=$WayKey?>"<?php Format::selected('way', $WayKey); ?>><?=$WayText?></option>
<?php    } ?>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="layout cat_list">
<?php
$x = 0;
reset($Categories);
foreach ($Categories as $CatKey => $CatName) {
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
                        <input type="checkbox" name="categories[<?=($CatKey+1)?>]" id="cat_<?=($CatKey+1)?>" value="1"<?php if (isset($_GET['categories'][$CatKey + 1])) { ?> checked="checked"<?php } ?> />
                        <label for="cat_<?=($CatKey + 1)?>"><?=$CatName?></label>
                    </td>
<?php
}
?>
                </tr>
            </table>
            <div class="submit">
                <input type="submit" value="Search torrents" />
            </div>
        </form>
    </div>
<?php    if (count($GroupIDs) === 0) { ?>
    <div class="center">
        Nothing found!
    </div>
<?php    } else { ?>
    <div class="linkbox"><?=$Pages?></div>
    <table class="torrent_table cats m_table" width="100%">
        <tr class="colhead">
            <td class="cats_col"></td>
            <td class="m_th_left"><a href="<?=header_link('Name', 'ASC')?>">Torrent</a></td>
            <td><a href="<?=header_link('Time')?>">Time</a></td>
            <td><a href="<?=header_link('Size')?>">Size</a></td>
            <td class="sign snatches m_th_right">
                <a href="<?=header_link('Snatched')?>">
                    <img src="static/styles/<?=$LoggedUser['StyleName']?>/images/snatched.png" class="tooltip" alt="Snatches" title="Snatches" />
                </a>
            </td>
            <td class="sign seeders m_th_right">
                <a href="<?=header_link('Seeders')?>">
                    <img src="static/styles/<?=$LoggedUser['StyleName']?>/images/seeders.png" class="tooltip" alt="Seeders" title="Seeders" />
                </a>
            </td>
            <td class="sign leechers m_th_right">
                <a href="<?=header_link('Leechers')?>">
                    <img src="static/styles/<?=$LoggedUser['StyleName']?>/images/leechers.png" class="tooltip" alt="Leechers" title="Leechers" />
                </a>
            </td>
        </tr>
<?php
    $PageSize = 0;
    foreach ($TorrentsInfo as $TorrentID => $Info) {
        list($GroupID, , $Time) = array_values($Info);

        extract(Torrents::array_group($Results[$GroupID]));
        $Torrent = $Torrents[$TorrentID];


        $TorrentTags = new Tags($TagList);

        if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
            unset($ExtendedArtists[2]);
            unset($ExtendedArtists[3]);
            $DisplayName = Artists::display_artists($ExtendedArtists);
        } elseif (!empty($Artists)) {
            $DisplayName = Artists::display_artists(array(1 => $Artists));
        } else {
            $DisplayName = '';
        }
        $DisplayName .= '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$TorrentID.'" class="tooltip" title="View torrent" dir="ltr">'.$GroupName.'</a>';
        if ($GroupYear > 0) {
            $DisplayName .= " [$GroupYear]";
        }
        if ($GroupVanityHouse) {
            $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }

        $ExtraInfo = Torrents::torrent_info($Torrent);
        if ($ExtraInfo) {
            $DisplayName .= " - $ExtraInfo";
        }
?>
        <tr class="torrent torrent_row<?=($Torrent['IsSnatched'] ? ' snatched_torrent' : '') . ($GroupFlags['IsSnatched'] ? ' snatched_group' : '')?>">
            <td class="center cats_col">
                <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($GroupCategoryID)?> <?=$TorrentTags->css_name()?>"></div>
            </td>
            <td class="td_info big_info">
<?php    if ($LoggedUser['CoverArt']) { ?>
                <div class="group_image float_left clear">
                    <?php ImageTools::cover_thumb($WikiImage, $GroupCategoryID) ?>
                </div>
<?php    } ?>
                <div class="group_info clear">
                    <span class="torrent_links_block">
                        [ <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download">DL</a>
                        | <a href="reportsv2.php?action=report&amp;id=<?=$TorrentID?>" class="tooltip" title="Report">RP</a> ]
                    </span>
                    <?php echo "$DisplayName\n"; ?>
<?php                    Votes::vote_link($GroupID, isset($UserVotes[$GroupID]) ? $UserVotes[$GroupID]['Type'] : ''); ?>
                    <div class="tags"><?=$TorrentTags->format('torrents.php?type='.$Action.'&amp;userid='.$UserID.'&amp;tags=')?></div>
                </div>
            </td>
            <td class="td_time nobr"><?=time_diff($Time, 1)?></td>
            <td class="td_size number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
            <td class="td_snatched m_td_right number_column"><?=number_format($Torrent['Snatched'])?></td>
            <td class="td_seeders m_td_right number_column<?=(($Torrent['Seeders'] == 0) ? ' r00' : '')?>"><?=number_format($Torrent['Seeders'])?></td>
            <td class="td_leechers m_td_right number_column"><?=number_format($Torrent['Leechers'])?></td>
        </tr>
<?php
    }?>
    </table>
<?php
} ?>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php View::show_footer(); ?>
