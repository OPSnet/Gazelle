<?php

$userMan = new Gazelle\Manager\User;
if (empty($_GET['userid'])) {
    $user = null;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}
$BookmarkView = false;

$header = new \Gazelle\Util\SortableTableHeader('created', [
    'year'     => ['dbColumn' => 'year',       'defaultSort' => 'desc', 'text' => 'Year'],
    'votes'    => ['dbColumn' => 'votes',      'defaultSort' => 'desc', 'text' => 'Votes'],
    'bounty'   => ['dbColumn' => 'bounty',     'defaultSort' => 'desc', 'text' => 'Bounty'],
    'filled'   => ['dbColumn' => 'timefilled', 'defaultSort' => 'desc', 'text' => 'Filled'],
    'created'  => ['dbColumn' => 'timeadded',  'defaultSort' => 'desc', 'text' => 'Created'],
    'lastvote' => ['dbColumn' => 'lastvote',   'defaultSort' => 'desc', 'text' => 'Last Vote'],
    'random'   => ['dbColumn' => 'RAND()',     'defaultSort' => ''],
]);
$OrderBy = $header->getOrderBy();

$SphQL = new SphinxqlQuery();
$SphQL->select('id, votes, bounty')->from('requests, requests_delta');
$SphQL->order_by($OrderBy, $header->getOrderDir());

$Submitted = !empty($_GET['submit']);

if (empty($_GET['type'])) {
    $Title = 'Requests';
} else {
    // Show filled defaults to on only for viewing types
    if (!$Submitted) {
        $_GET['show_filled'] = "on";
    }
    switch ($_GET['type']) {
        case 'created':
            if ($user) {
                if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                    error(403);
                }
                $Title = "Requests created by " . $user->username();
                $SphQL->where('userid', $user->id());
            } else {
                $Title = 'Your requests';
                $SphQL->where('userid', $Viewer->id());
            }
            break;
        case 'voted':
            if ($user) {
                if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                    error(403);
                }
                $Title = "Requests voted for by " . $user->username();
                $SphQL->where('voter', $user->id());
            } else {
                $Title = 'Requests you have voted on';
                $SphQL->where('voter', $Viewer->id());
            }
            break;
        case 'filled':
            if ($user) {
                if (!$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                    error(403);
                }
                $Title = "Requests filled by " . $user->username();
                $SphQL->where('fillerid', $user->id());
            } else {
                $Title = 'Requests you have filled';
                $SphQL->where('fillerid', $Viewer->id());
            }
            break;
        case 'bookmarks':
            $Title = 'Your bookmarked requests';
            $BookmarkView = true;
            $SphQL->where('bookmarker', $Viewer->id());
            break;
        default:
            error(404);
    }
}

if ($Submitted && empty($_GET['showall'])) {
    $SphQL->where('visible', 1);
}

// We don't want to show filled by default on plain requests.php,
// but we do show it by default if viewing a $_GET['type'] page
// by default
if ((!$Submitted && empty($_GET['type'])) || ($Submitted && empty($_GET['show_filled']))) {
    $SphQL->where('torrentid', 0);
}

$EnableNegation = false; // Sphinx needs at least one positive search condition to support the NOT operator

if (!empty($_GET['formats'])) {
    $FormatArray = $_GET['formats'];
    if (count($FormatArray) !== count($Formats)) {
        $FormatNameArray = [];
        foreach ($FormatArray as $Index => $MasterIndex) {
            if (isset($Formats[$MasterIndex])) {
                $FormatNameArray[$Index] = '"' . strtr(Sphinxql::sph_escape_string($Formats[$MasterIndex]), '-.', '  ') . '"';
            }
        }
        if (count($FormatNameArray) >= 1) {
            $EnableNegation = true;
            if (!empty($_GET['formats_strict'])) {
                $SearchString = '(' . implode(' | ', $FormatNameArray) . ')';
            } else {
                $SearchString = '(any | ' . implode(' | ', $FormatNameArray) . ')';
            }
            $SphQL->where_match($SearchString, 'formatlist', false);
        }
    }
}

if (!empty($_GET['media'])) {
    $MediaArray = $_GET['media'];
    if (count($MediaArray) !== count($Media)) {
        $MediaNameArray = [];
        foreach ($MediaArray as $Index => $MasterIndex) {
            if (isset($Media[$MasterIndex])) {
                $MediaNameArray[$Index] = '"' . strtr(Sphinxql::sph_escape_string($Media[$MasterIndex]), '-.', '  ') . '"';
            }
        }

        if (count($MediaNameArray) >= 1) {
            $EnableNegation = true;
            if (!empty($_GET['media_strict'])) {
                $SearchString = '(' . implode(' | ', $MediaNameArray) . ')';
            } else {
                $SearchString = '(any | ' . implode(' | ', $MediaNameArray) . ')';
            }
            $SphQL->where_match($SearchString, 'medialist', false);
        }
    }
}

if (!empty($_GET['bitrates'])) {
    $BitrateArray = $_GET['bitrates'];
    if (count($BitrateArray) !== count($Bitrates)) {
        $BitrateNameArray = [];
        foreach ($BitrateArray as $Index => $MasterIndex) {
            if (isset($Bitrates[$MasterIndex])) {
                $BitrateNameArray[$Index] = '"' . strtr(Sphinxql::sph_escape_string($Bitrates[$MasterIndex]), '-.', '  ') . '"';
            }
        }

        if (count($BitrateNameArray) >= 1) {
            $EnableNegation = true;
            if (!empty($_GET['bitrate_strict'])) {
                $SearchString = '(' . implode(' | ', $BitrateNameArray) . ')';
            } else {
                $SearchString = '(any | ' . implode(' | ', $BitrateNameArray) . ')';
            }
            $SphQL->where_match($SearchString, 'bitratelist', false);
        }
    }
}

if (!empty($_GET['search'])) {
    $SearchString = trim($_GET['search']);

    if ($SearchString !== '') {
        $SearchWords = ['include' => [], 'exclude' => []];
        $Words = explode(' ', $SearchString);
        foreach ($Words as $Word) {
            $Word = trim($Word);
            // Skip isolated hyphens to enable "Artist - Title" searches
            if ($Word === '-') {
                continue;
            }
            if ($Word[0] === '!' && strlen($Word) >= 2) {
                if (strpos($Word, '!', 1) === false) {
                    $SearchWords['exclude'][] = $Word;
                } else {
                    $SearchWords['include'][] = $Word;
                    $EnableNegation = true;
                }
            } elseif ($Word !== '') {
                $SearchWords['include'][] = $Word;
                $EnableNegation = true;
            }
        }
    }
}

if (!isset($_GET['tags_type']) || $_GET['tags_type'] === '1') {
    $TagType = 1;
    $_GET['tags_type'] = '1';
} else {
    $TagType = 0;
    $_GET['tags_type'] = '0';
}

if (!empty($_GET['tags'])) {
    $SearchTags = ['include' => [], 'exclude' => []];
    $Tags = explode(',', str_replace('.', '_', $_GET['tags']));
    foreach ($Tags as $Tag) {
        $Tag = trim($Tag);
        if ($Tag[0] === '!' && strlen($Tag) >= 2) {
            if (strpos($Tag, '!', 1) === false) {
                $SearchTags['exclude'][] = $Tag;
            } else {
                $SearchTags['include'][] = $Tag;
                $EnableNegation = true;
            }
        } elseif ($Tag !== '') {
            $SearchTags['include'][] = $Tag;
            $EnableNegation = true;
        }
    }

    $TagFilter = Tags::tag_filter_sph($SearchTags, $EnableNegation, $TagType);
    $TagNames = $TagFilter['input'];

    if (!empty($TagFilter['predicate'])) {
        $SphQL->where_match($TagFilter['predicate'], 'taglist', false);
    }

} elseif (!isset($_GET['tags_type']) || $_GET['tags_type'] !== '0') {
    $_GET['tags_type'] = 1;
} else {
    $_GET['tags_type'] = 0;
}

if (isset($SearchWords)) {
    $QueryParts = [];
    if (!$EnableNegation && !empty($SearchWords['exclude'])) {
        $SearchWords['include'] = array_merge($SearchWords['include'], $SearchWords['exclude']);
        unset($SearchWords['exclude']);
    }
    foreach ($SearchWords['include'] as $Word) {
        $QueryParts[] = Sphinxql::sph_escape_string($Word);
    }
    if (!empty($SearchWords['exclude'])) {
        foreach ($SearchWords['exclude'] as $Word) {
            $QueryParts[] = '!' . Sphinxql::sph_escape_string(substr($Word, 1));
        }
    }
    if (!empty($QueryParts)) {
        $SearchString = implode(' ', $QueryParts);
        $SphQL->where_match($SearchString, '*', false);
    }
}

if (!empty($_GET['filter_cat'])) {
    $CategoryArray = array_keys($_GET['filter_cat']);
    if (count($CategoryArray) !== count(CATEGORY)) {
        foreach ($CategoryArray as $Key => $Index) {
            if (!isset(CATEGORY[$Index - 1])) {
                unset($CategoryArray[$Key]);
            }
        }
        if (count($CategoryArray) >= 1) {
            $SphQL->where('categoryid', $CategoryArray);
        }
    }
}

$releaseTypes = (new \Gazelle\ReleaseType)->list();
if (!empty($_GET['releases'])) {
    $ReleaseArray = $_GET['releases'];
    if (count($ReleaseArray) !== count($releaseTypes)) {
        foreach ($ReleaseArray as $Index => $Value) {
            if (!isset($releaseTypes[$Value])) {
                unset($ReleaseArray[$Index]);
            }
        }
        if (count($ReleaseArray) >= 1) {
            $SphQL->where('releasetype', $ReleaseArray);
        }
    }
}

if (!empty($_GET['requestor'])) {
    if (intval($_GET['requestor'])) {
        $SphQL->where('userid', $_GET['requestor']);
    } else {
        error(404);
    }
}

if (isset($_GET['year'])) {
    if (intval($_GET['year']) || $_GET['year'] === '0') {
        $SphQL->where('year', $_GET['year']);
    } else {
        error(404);
    }
}

if ($OrderBy !== 'random' && isset($_GET['page']) && intval($_GET['page']) && $_GET['page'] > 0) {
    $Page = $_GET['page'];
    $Offset = ($Page - 1) * REQUESTS_PER_PAGE;
    $SphQL->limit($Offset, REQUESTS_PER_PAGE, $Offset + REQUESTS_PER_PAGE);
} else {
    $Page = 1;
    $SphQL->limit(0, REQUESTS_PER_PAGE, REQUESTS_PER_PAGE);
}

$SphQLResult = $SphQL->query();
$NumResults = (int)$SphQLResult->get_meta('total_found');
if ($NumResults > 0) {
    $SphRequests = $SphQLResult->to_array('id');
    if ($OrderBy === 'random') {
        $NumResults = count($SphRequests);
    }
    if ($NumResults > REQUESTS_PER_PAGE) {
        if (($Page - 1) * REQUESTS_PER_PAGE > $NumResults) {
            $Page = 0;
        }
        $PageLinks = Format::get_pages($Page, $NumResults, REQUESTS_PER_PAGE);
    }
}

$CurrentURL = Format::get_url(['order', 'sort', 'page']);
View::show_header($Title, 'requests');

?>
<div class="thin">
    <div class="header">
        <h2><?=$Title?></h2>
    </div>
    <div class="linkbox">
<?php    if (!$BookmarkView) {
        if (check_perms('site_submit_requests')) { ?>
        <a href="requests.php?action=new" class="brackets">New request</a>
        <a href="requests.php?type=created" class="brackets">My requests</a>
<?php        }
        if (check_perms('site_vote')) { ?>
        <a href="requests.php?type=voted" class="brackets">Requests I've voted on</a>
<?php        } ?>
        <a href="bookmarks.php?type=requests" class="brackets">Bookmarked requests</a>
<?php    } else { ?>
        <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
        <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
        <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
        <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
<?php    } ?>
    </div>
<?php    if ($BookmarkView && $NumResults === 0) { ?>
    <div class="box pad" align="center">
        <h2>You have not bookmarked any requests.</h2>
    </div>
<?php    } else { ?>
    <form class="search_form" name="requests" action="" method="get">
<?php        if ($BookmarkView) { ?>
        <input type="hidden" name="action" value="view" />
        <input type="hidden" name="type" value="requests" />
<?php        } elseif (isset($_GET['type'])) { ?>
        <input type="hidden" name="type" value="<?=$_GET['type']?>" />
<?php        } ?>
        <input type="hidden" name="submit" value="true" />
<?php        if (!empty($_GET['userid']) && intval($_GET['userid'])) { ?>
        <input type="hidden" name="userid" value="<?=$_GET['userid']?>" />
<?php        } ?>
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr id="search_terms">
                <td class="label">Search terms:</td>
                <td>
                    <input type="search" name="search" size="75" value="<?php if (isset($_GET['search'])) { echo display_str($_GET['search']); } ?>" />
                </td>
            </tr>
            <tr id="tagfilter">
                <td class="label">Tags (comma-separated):</td>
                <td>
                    <input type="search" name="tags" id="tags" size="60" value="<?=!empty($TagNames) ? display_str($TagNames) : ''?>"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />&nbsp;
                    <input type="radio" name="tags_type" id="tags_type0" value="0"<?php Format::selected('tags_type', 0, 'checked')?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                    <input type="radio" name="tags_type" id="tags_type1" value="1"<?php Format::selected('tags_type', 1, 'checked')?> /><label for="tags_type1"> All</label>
                </td>
            </tr>
            <tr id="include_filled">
                <td class="label"><label for="include_filled_box">Include filled:</label></td>
                <td>
                    <input type="checkbox" id="include_filled_box" name="show_filled"<?php
                        if (!empty($_GET['show_filled']) || (!$Submitted && !empty($_GET['type']) && $_GET['type'] === 'filled')) { ?> checked="checked"<?php } ?> />
                </td>
            </tr>
            <tr id="include_old">
                <td class="label"><label for="include_old_box">Include old:</label></td>
                <td>
                    <input type="checkbox" id="include_old_box" name="showall"<?php if (!$Submitted || !empty($_GET['showall'])) { ?> checked="checked"<?php } ?> />
                </td>
            </tr>
<?php        /* ?>
            <tr>
                <td class="label">Requested by:</td>
                <td>
                    <input type="search" name="requester" size="75" value="<?=display_str($_GET['requester'])?>" />
                </td>
            </tr>
<?        */ ?>
        </table>
        <table class="layout cat_list">
<?php
        $x = 1;
        foreach (CATEGORY as $CatKey => $CatName) {
            if ($x % 8 === 0 || $x === 1) {
?>
                <tr>
<?php            } ?>
                    <td>
                        <input type="checkbox" name="filter_cat[<?=($CatKey + 1) ?>]" id="cat_<?=($CatKey + 1) ?>" value="1"<?php if (isset($_GET['filter_cat'][$CatKey + 1])) { ?> checked="checked"<?php } ?> />
                        <label for="cat_<?=($CatKey + 1) ?>"><?=$CatName?></label>
                    </td>
<?php            if ($x % 7 === 0) { ?>
                </tr>
<?php
            }
            $x++;
        }
?>
        </table>
        <table class="layout">
            <tr id="release_list">
                <td class="label">Release types</td>
                <td>
                    <input type="checkbox" id="toggle_releases" onchange="Toggle('releases', 0);"<?=(!$Submitted || !empty($ReleaseArray) && count($ReleaseArray) === count($releaseTypes) ? ' checked="checked"' : '') ?> /> <label for="toggle_releases">All</label>
<?php
        $i = 0;
        foreach ($releaseTypes as $Key => $Val) {
            if ($i % 8 === 0) {
                echo '<br />';
            }
?>
                    <input type="checkbox" name="releases[]" value="<?=$Key?>" id="release_<?=$Key?>"
                        <?=(!$Submitted || (!empty($ReleaseArray) && in_array($Key, $ReleaseArray)) ? ' checked="checked" ' : '')?>
                    /> <label for="release_<?=$Key?>"><?=$Val?></label>
<?php
            $i++;
        }
?>
                </td>
            </tr>
            <tr id="format_list">
                <td class="label">Formats</td>
                <td>
                    <input type="checkbox" id="toggle_formats" onchange="Toggle('formats', 0);"<?=(!$Submitted || !empty($FormatArray) && count($FormatArray) === count($Formats) ? ' checked="checked"' : '') ?> />
                    <label for="toggle_formats">All</label>
                    <input type="checkbox" id="formats_strict" name="formats_strict"<?=(!empty($_GET['formats_strict']) ? ' checked="checked"' : '')?> />
                    <label for="formats_strict">Only specified</label>
<?php
        foreach ($Formats as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            }
?>
                    <input type="checkbox" name="formats[]" value="<?=$Key?>" id="format_<?=$Key?>"
                        <?=(!$Submitted || (!empty($FormatArray) && in_array($Key, $FormatArray)) ? ' checked="checked" ' : '')?>
                    /> <label for="format_<?=$Key?>"><?=$Val?></label>
<?php        } ?>
                </td>
            </tr>
            <tr id="bitrate_list">
                <td class="label">Bitrates</td>
                <td>
                    <input type="checkbox" id="toggle_bitrates" onchange="Toggle('bitrates', 0);"<?=(!$Submitted || !empty($BitrateArray) && count($BitrateArray) === count($Bitrates) ? ' checked="checked"' : '')?> />
                    <label for="toggle_bitrates">All</label>
                    <input type="checkbox" id="bitrate_strict" name="bitrate_strict"<?=(!empty($_GET['bitrate_strict']) ? ' checked="checked"' : '') ?> />
                    <label for="bitrate_strict">Only specified</label>
<?php
        foreach ($Bitrates as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            }
?>
                    <input type="checkbox" name="bitrates[]" value="<?=$Key?>" id="bitrate_<?=$Key?>"
                        <?=(!$Submitted || (!empty($BitrateArray) && in_array($Key, $BitrateArray)) ? ' checked="checked" ' : '')?>
                    /> <label for="bitrate_<?=$Key?>"><?=$Val?></label>
<?php
        }
?>
                </td>
            </tr>
            <tr id="media_list">
                <td class="label">Media</td>
                <td>
                    <input type="checkbox" id="toggle_media" onchange="Toggle('media', 0);"<?=(!$Submitted || !empty($MediaArray) && count($MediaArray) === count($Media) ? ' checked="checked"' : '')?> />
                    <label for="toggle_media">All</label>
                    <input type="checkbox" id="media_strict" name="media_strict"<?=(!empty($_GET['media_strict']) ? ' checked="checked"' : '')?> />
                    <label for="media_strict">Only specified</label>
<?php
        foreach ($Media as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            }
?>
                    <input type="checkbox" name="media[]" value="<?=$Key?>" id="media_<?=$Key?>"
                        <?=(!$Submitted || (!empty($MediaArray) && in_array($Key, $MediaArray)) ? ' checked="checked" ' : '')?>
                    /> <label for="media_<?=$Key?>"><?=$Val?></label>
<?php        } ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Search requests" />
                </td>
            </tr>
        </table>
    </form>
<?php        if (isset($PageLinks)) { ?>
    <div class="linkbox">
        <?= $PageLinks ?>
    </div>
<?php        } ?>
    <table id="request_table" class="request_table border m_table" cellpadding="6" cellspacing="1" border="0" width="100%">
        <tr class="colhead_dark">
            <td style="width: 38%;" class="m_th_left nobr">
                Request Name / <?= $header->emit('year') ?>
            </td>
            <td class="m_th_right nobr">
                <?= $header->emit('votes') ?>
            </td>
            <td class="m_th_right nobr">
                <?= $header->emit('bounty') ?>
            </td>
            <td class="nobr">
                <?= $header->emit('filled') ?>
            </td>
            <td class="nobr">
                Filled by
            </td>
            <td class="nobr">
                Requested by
            </td>
            <td class="nobr">
                <?= $header->emit('created') ?>
            </td>
            <td class="nobr">
                <?= $header->emit('lastvote') ?>
            </td>
        </tr>
<?php
        if ($NumResults === 0) {
            // not viewing bookmarks but no requests found
?>
        <tr class="rowb">
            <td colspan="8">
                Nothing found!
            </td>
        </tr>
<?php        } elseif ($Page === 0) { ?>
        <tr class="rowb">
            <td colspan="8">
                The requested page contains no matches!
            </td>
        </tr>
<?php
        } else {

    $Requests = Requests::get_requests(array_keys($SphRequests));
    foreach ($Requests as $RequestID => $Request) {
        $SphRequest = $SphRequests[$RequestID];
        $Bounty = $SphRequest['bounty'] * 1024; // Sphinx stores bounty in kB
        $VoteCount = $SphRequest['votes'];

        if ($Request['CategoryID'] == 0) {
            $CategoryName = 'Unknown';
        } else {
            $CategoryName = CATEGORY[$Request['CategoryID'] - 1];
        }

        if ($Request['TorrentID'] != 0) {
            $IsFilled = true;
            $Filler = $userMan->findById($Request['FillerID']);
        } else {
            $IsFilled = false;
        }

        if ($CategoryName === 'Music') {
            $ArtistForm = Requests::get_artists($RequestID);
            $ArtistLink = Artists::display_artists($ArtistForm, true, true);
            $FullName = "$ArtistLink<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">{$Request['Title']}</span> [{$Request['Year']}]</a>";
        } elseif ($CategoryName === 'Audiobooks' || $CategoryName === 'Comedy') {
            $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">{$Request['Title']}</span> [{$Request['Year']}]</a>";
        } else {
            $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\" dir=\"ltr\">{$Request['Title']}</a>";
        }
        $Tags = $Request['Tags'];
?>
        <tr class="row<?=($i % 2 ? 'b' : 'a')?>">
            <td>
                <?=$FullName?>
                <div class="tags">
<?php
        $TagList = [];
        foreach ($Request['Tags'] as $TagID => $TagName) {
            $TagList[] = '<a href="?tags='.$TagName.($BookmarkView ? '&amp;type=requests' : '').'">'.display_str($TagName).'</a>';
        }
        $TagList = implode(', ', $TagList);
?>
                    <?=$TagList?>
                </div>
            </td>
            <td class="m_td_right nobr">
                <span id="vote_count_<?=$RequestID?>"><?=number_format($VoteCount)?></span>
<?php         if (!$IsFilled && check_perms('site_vote')) { ?>
                &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets"><strong>+</strong></a>
<?php        } ?>
            </td>
            <td class="m_td_right number_column nobr">
                <?=Format::get_size($Bounty)?>
            </td>
            <td class="m_hidden nobr">
<?php        if ($IsFilled) { ?>
                <a href="torrents.php?torrentid=<?= $Request['TorrentID'] ?>"><strong><?=time_diff($Request['TimeFilled'], 1)?></strong></a>
<?php        } else { ?>
                <strong>No</strong>
<?php        } ?>
            </td>
            <td>
<?php        if ($IsFilled) { ?>
                <a href="user.php?id=<?= $Filler->id() ?>"><?= $Filler->username() ?></a>
<?php        } else { ?>
                &mdash;
<?php        } ?>
            </td>
            <td>
                <?=Users::format_username($Request['UserID'], false, false, false)?>
            </td>
            <td class="nobr">
                <?=time_diff($Request['TimeAdded'], 1)?>
            </td>
            <td class="nobr">
                <?=time_diff($Request['LastVote'], 1)?>
            </td>
        </tr>
<?php
    } // foreach
        } // else
    } // if ($BookmarkView && $NumResults < 1)
?>
    </table>
<?php if (isset($PageLinks)) { ?>
    <div class="linkbox">
        <?=$PageLinks?>
    </div>
<?php } ?>
</div>
<?php
View::show_footer();
