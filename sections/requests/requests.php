<?php

$userMan = new Gazelle\Manager\User;
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}
$ownProfile = $Viewer->id() === $user->id();

$header = new \Gazelle\Util\SortableTableHeader('created', [
    'year'     => ['dbColumn' => 'year',       'defaultSort' => 'desc', 'text' => 'Year'],
    'votes'    => ['dbColumn' => 'votes',      'defaultSort' => 'desc', 'text' => 'Votes'],
    'bounty'   => ['dbColumn' => 'bounty',     'defaultSort' => 'desc', 'text' => 'Bounty'],
    'filled'   => ['dbColumn' => 'timefilled', 'defaultSort' => 'desc', 'text' => 'Filled'],
    'created'  => ['dbColumn' => 'timeadded',  'defaultSort' => 'desc', 'text' => 'Created'],
    'lastvote' => ['dbColumn' => 'lastvote',   'defaultSort' => 'desc', 'text' => 'Last Vote'],
    'random'   => ['dbColumn' => 'RAND()',     'defaultSort' => ''],
]);

$search = new Gazelle\Search\Request(new Gazelle\Manager\Request);
$submitted = isset($_GET['submit']);
$bookmarkView = false;

if (empty($_GET['type'])) {
    $Title = 'Requests';
} else {
    // Show filled defaults to on only for viewing types
    if (!$submitted) {
        $_GET['show_filled'] = "on";
    }
    switch ($_GET['type']) {
        case 'bookmarks':
            $search->setBookmarker($user);
            $bookmarkView = true;
            break;
        case 'created':
            if (!$ownProfile && !$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                error(403);
            }
            $search->setCreator($user);
            break;
        case 'voted':
            if (!$ownProfile && !$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                error(403);
            }
            $search->setVoter($user);
            break;
        case 'filled':
            if (!$ownProfile && !$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                error(403);
            }
            $search->setFiller($user);
            break;
        default:
            error(404);
    }
}

if ($submitted && empty($_GET['showall'])) {
    $search->setVisible(true);
}

// We don't want to show filled by default on plain requests.php,
// but we do show it by default if viewing a $_GET['type'] page
if ((!$submitted && !isset($_GET['type'])) || ($submitted && !isset($_GET['show_filled']))) {
    $search->showUnfilled();
}
$releaseTypes = (new \Gazelle\ReleaseType)->list();
$search->setFormat($_GET['formats'] ?? [], isset($_GET['formats_strict']))
    ->setMedia($_GET['media'] ?? [], isset($_GET['media_strict']))
    ->setEncoding($_GET['bitrates'] ?? [], isset($_GET['bitrate_strict']))
    ->setText($_GET['search'] ?? '')
    ->setTag($_GET['tags'] ?? '', $_GET['tag_mode'] ?? 'all')
    ->setCategory($_GET['filter_cat'] ?? [])
    ->setReleaseType($_GET['releases'] ?? [], $releaseTypes);

if (!empty($_GET['requestor'])) {
    $requestor = (int)$_GET['requestor'];
    if ($requestor) {
        $search->setRequestor($requestor);
    } else {
        error(404);
    }
}

if (isset($_GET['year'])) {
    $search->setYear((int)$_GET['year']);
}

$paginator = new Gazelle\Util\Paginator(REQUESTS_PER_PAGE, (int)($_GET['page'] ?? 1));
if ($header->getOrderBy() === 'random') {
    $search->limit(0, REQUESTS_PER_PAGE, REQUESTS_PER_PAGE);
} else {
    $offset = ($paginator->page() - 1) * REQUESTS_PER_PAGE;
    $search->limit($offset, REQUESTS_PER_PAGE, $offset + REQUESTS_PER_PAGE);
}

$search->execute($header->getOrderBy(), $header->getOrderDir());
$paginator->setTotal($search->total());

$CurrentURL = Format::get_url(['order', 'sort', 'page']);
View::show_header($search->text(), ['js' => 'requests']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $search->title() ?></h2>
    </div>
    <div class="linkbox">
<?php
if (!$bookmarkView) {
        if ($Viewer->permitted('site_submit_requests')) {
?>
        <a href="requests.php?action=new" class="brackets">New request</a>
        <a href="requests.php?type=created" class="brackets">My requests</a>
<?php
    }
    if ($Viewer->permitted('site_vote')) {
?>
        <a href="requests.php?type=voted" class="brackets">Requests I've voted on</a>
<?php } ?>
        <a href="bookmarks.php?type=requests" class="brackets">Bookmarked requests</a>
<?php } else { ?>
        <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
        <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
        <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
        <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
<?php } ?>
    </div>
<?php if ($bookmarkView && !$search->total()) { ?>
    <div class="box pad" align="center">
        <h2>You have not bookmarked any requests.</h2>
    </div>
<?php } else { ?>
    <form class="search_form" name="requests" action="" method="get">
<?php if ($bookmarkView) { ?>
        <input type="hidden" name="action" value="view" />
        <input type="hidden" name="type" value="requests" />
<?php } elseif (isset($_GET['type'])) { ?>
        <input type="hidden" name="type" value="<?=$_GET['type']?>" />
<?php } ?>
        <input type="hidden" name="submit" value="true" />
<?php if (!empty($_GET['userid']) && intval($_GET['userid'])) { ?>
        <input type="hidden" name="userid" value="<?=$_GET['userid']?>" />
<?php } ?>
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
                    <input type="search" name="tags" id="tags" size="60" value="<?= display_str($search->tagList()) ?>"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />&nbsp;
                    <label><input type="radio" name="tag_mode" value="any"<?= ($_GET['tag_mode'] ?? '') === 'any' ? ' checked="checked"' : '' ?> /> Any</label>&nbsp;&nbsp;
                    <label><input type="radio" name="tag_mode" value="all"<?= ($_GET['tag_mode'] ?? 'all') === 'all' ? ' checked="checked"' : '' ?> /> All</label>
                </td>
            </tr>
            <tr id="include_filled">
                <td class="label"><label for="include_filled_box">Include filled:</label></td>
                <td>
                    <input type="checkbox" id="include_filled_box" name="show_filled"<?php
                        if (isset($_GET['show_filled']) || (!$submitted && isset($_GET['type']) && $_GET['type'] === 'filled')) { ?> checked="checked"<?php } ?> />
                </td>
            </tr>
            <tr id="include_old">
                <td class="label"><label for="include_old_box">Include old:</label></td>
                <td>
                    <input type="checkbox" id="include_old_box" name="showall"<?php if (!$submitted || isset($_GET['showall'])) { ?> checked="checked"<?php } ?> />
                </td>
            </tr>
<?php        /* ?>
            <tr>
                <td class="label">Requested by:</td>
                <td>
                    <input type="search" name="requestor" size="75" value="<?=display_str($_GET['requestor'])?>" />
                </td>
            </tr>
<?        */ ?>
        </table>
        <table class="layout">
<?php   $releaseTypeList = $search->releaseTypeList(); ?>
            <tr id="cat_list">
                <td class="label">Categories</td>
                <td>
<?php   foreach (CATEGORY as $CatKey => $CatName) { ?>
                    <label class="req-choice">
                    <input type="checkbox" name="filter_cat[<?=($CatKey + 1) ?>]" id="cat_<?=($CatKey + 1) ?>" value="1"<?=
                        !$submitted || isset($_GET['filter_cat'][$CatKey + 1]) ? ' checked="checked"' : '' ?> /> <?= $CatName ?></label>
<?php   } ?>
                </td>
            </tr>
            <tr id="release_list">
                <td class="label">Release types</td>
                <td>
                    <input type="checkbox" id="toggle_releases" onchange="Toggle('releases', 0);"<?=
                        !$submitted || in_array(count($releaseTypeList), [0, count($releaseTypes)]) ? ' checked="checked"' : '' ?> /> <label for="toggle_releases">All</label>
                    <br />
<?php   foreach ($releaseTypes as $Key => $Val) { ?>
                    <label class="req-choice">
                    <input type="checkbox" name="releases[]" value="<?=$Key?>" id="release_<?=$Key?>"
                        <?= !$submitted || !$releaseTypeList || in_array($Key, $releaseTypeList) ? ' checked="checked" ' : '' ?>
                    /> <?= $Val ?></label>
<?php   } ?>
                </td>
            </tr>
<?php   $formatList = $search->formatList(); ?>
            <tr id="format_list">
                <td class="label">Formats</td>
                <td>
                    <input type="checkbox" id="toggle_formats" onchange="Toggle('formats', 0);"<?=
                        !$submitted || in_array(count($formatList), [0, count(FORMAT)]) ? ' checked="checked"' : '' ?> />
                    <label for="toggle_formats">All</label>
                    <input type="checkbox" id="formats_strict" name="formats_strict"<?=(!empty($_GET['formats_strict']) ? ' checked="checked"' : '')?> />
                    <label for="formats_strict">Only specified</label>
                    <br />
<?php   foreach (FORMAT as $Key => $Val) { ?>
                    <label class="req-choice">
                    <input type="checkbox" name="formats[]" value="<?=$Key?>" id="format_<?=$Key?>"
                        <?= !$submitted || !$formatList || in_array(strtolower($Val), $formatList) ? ' checked="checked" ' : '' ?>
                    /> <?= $Val ?></label>
<?php    } ?>
                </td>
            </tr>
<?php   $encodingList = $search->encodingList(); ?>
            <tr id="bitrate_list">
                <td class="label">Encoding</td>
                <td>
                    <input type="checkbox" id="toggle_bitrates" onchange="Toggle('bitrates', 0);"<?=
                        !$submitted || in_array(count($encodingList), [0, count(ENCODING)]) ? ' checked="checked"' : '' ?> />
                    <label for="toggle_bitrates">All</label>
                    <input type="checkbox" id="bitrate_strict" name="bitrate_strict"<?=(!empty($_GET['bitrate_strict']) ? ' checked="checked"' : '') ?> />
                    <label for="bitrate_strict">Only specified</label>
                    <br />
<?php   foreach (ENCODING as $Key => $Val) { ?>
                    <label class="req-choice">
                    <input type="checkbox" name="bitrates[]" value="<?=$Key?>" id="bitrate_<?=$Key?>"
                        <?= !$submitted || !$encodingList || in_array(strtolower($Val), $encodingList) ? ' checked="checked" ' : '' ?>
                    /> <?= $Val ?></label>
<?php   } ?>
                </td>
            </tr>
<?php   $mediaList = $search->mediaList(); ?>
            <tr id="media_list">
                <td class="label">Media</td>
                <td>
                    <input type="checkbox" id="toggle_media" onchange="Toggle('media', 0);"<?=
                        !$submitted || in_array(count($mediaList), [0, count(MEDIA)]) ? ' checked="checked"' : '' ?> />
                    <label for="toggle_media">All</label>
                    <input type="checkbox" id="media_strict" name="media_strict"<?=(!empty($_GET['media_strict']) ? ' checked="checked"' : '')?> />
                    <label for="media_strict">Only specified</label>
                    <br />
<?php   foreach (MEDIA as $Key => $Val) { ?>
                    <label class="req-choice">
                    <input type="checkbox" name="media[]" value="<?=$Key?>" id="media_<?=$Key?>"
                        <?= !$submitted || !$mediaList || in_array(strtolower($Val), $mediaList) ? ' checked="checked" ' : '' ?>
                    /> <?= $Val ?></label>
<?php   } ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Search requests" />
                </td>
            </tr>
        </table>
    </form>
<?= $paginator->linkbox() ?>
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
    if ($search->total() === 0) {
            // not viewing bookmarks but no requests found
?>
        <tr class="rowb">
            <td colspan="8">
                Nothing found!
            </td>
        </tr>
<?php } elseif ($search->total() == 0) { ?>
        <tr class="rowb">
            <td colspan="8">
                The requested page contains no matches!
            </td>
        </tr>
<?php
    } else {
        $row = 0;
        foreach ($search->list() as $request) {
            $requestId = $request->id();
?>
                <tr class="row<?= ++$row % 2 ? 'b' : 'a' ?>">
                    <td>
                        <?=$request->smartLink() ?>
                        <div class="tags">
<?php
            $TagList = [];
            foreach ($request->tagNameList() as $tagName) {
                $TagList[] = '<a href="?tags='.$tagName.($bookmarkView ? '&amp;type=requests' : '').'">'.display_str($tagName).'</a>';
            }
?>
                            <?= implode(', ', $TagList) ?>
                        </div>
                    </td>
                    <td class="m_td_right nobr">
                        <span id="vote_count_<?=$requestId?>"><?=number_format($request->userVotedTotal())?></span>
<?php       if (!$request->isFilled() && $Viewer->permitted('site_vote')) { ?>
                        &nbsp;&nbsp; <a href="javascript:Vote(0, <?= $requestId ?>)" class="brackets"><strong>+</strong></a>
<?php       } ?>
                    </td>
                    <td class="m_td_right number_column nobr">
                        <?=Format::get_size($request->bountyTotal())?>
                    </td>
                    <td class="m_hidden nobr">
<?php       if ($request->isFilled()) { ?>
                        <a href="torrents.php?torrentid=<?= $request->torrentId() ?>"><strong><?= time_diff($request->fillDate(), 1)?></strong></a>
<?php       } else { ?>
                        <strong>No</strong>
<?php       } ?>
                    </td>
                    <td>
<?php       if ($request->isFilled()) { ?>
                        <?= $userMan->findById($request->fillerId())?->link() ?? 'System' ?>
<?php       } else { ?>
                        &mdash;
<?php       } ?>
                    </td>
                    <td>
                        <?= $userMan->findById($request->userId())?->link() ?>
                    </td>
                    <td class="nobr">
                        <?=time_diff($request->created(), 1)?>
                    </td>
                    <td class="nobr">
                        <?=time_diff($request->lastVoteDate(), 1)?>
                    </td>
                </tr>
<?php
        } // foreach
    } // else
} // if ($bookmarkView && total == 0)
?>
    </table>
<?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
