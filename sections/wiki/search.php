<?php

use Cake\Collection\Iterator\SortIterator;
use Gazelle\Util\SortableTableHeader;

if (empty($_GET['nojump'])) {
    $ArticleID = Wiki::alias_to_id($_GET['search']);
    if ($ArticleID) {
        //Found the article!
        header('Location: wiki.php?action=article&id='.$ArticleID);
        die();
    }
}

define('ARTICLES_PER_PAGE', 25);
list($Page, $Limit) = Format::page_limit(ARTICLES_PER_PAGE);

$SortOrderMap = [
    'title'   => ['Title', 'asc'],
    'created' => ['ID', 'desc'],
    'edited'  => ['Date', 'desc'],
];
$SortOrder = (!empty($_GET['order']) && isset($SortOrderMap[$_GET['order']])) ? $_GET['order'] : 'created';
$OrderBy = $SortOrderMap[$SortOrder][0];
$OrderWay = (empty($_GET['sort']) || $_GET['sort'] == $SortOrderMap[$SortOrder][1])
    ? $SortOrderMap[$SortOrder][1]
    : SortableTableHeader::SORT_DIRS[$SortOrderMap[$SortOrder][1]];

$TypeMap = [
    'title' => 'Title',
    'body'  => 'Body',
];
$Type = (!empty($_GET['type']) && isset($TypeMap[$_GET['type']])) ? $TypeMap[$_GET['type']] : 'Title';

// What are we looking for? Let's make sure it isn't dangerous.
$Search = db_string(trim($_GET['search']));

// Break search string down into individual words
$Words = explode(' ', $Search);

$SQL = "
    SELECT
        SQL_CALC_FOUND_ROWS
        ID,
        Title,
        Date,
        Author
    FROM wiki_articles
    WHERE MinClassRead <= '".$LoggedUser['EffectiveClass']."'";
if ($Search != '') {
    $SQL .= " AND $Type LIKE '%";
    $SQL .= implode("%' AND $Type LIKE '%", $Words);
    $SQL .= "%' ";
}

$SQL .= "
    ORDER BY $OrderBy $OrderWay
    LIMIT $Limit ";
$RS = $DB->query($SQL);
$DB->query("
    SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();

View::show_header('Search articles');
$DB->set_query_id($RS);
?>
<div class="thin">
    <div class="header">
        <h2>Search articles</h2>
        <div class="linkbox">
            <a href="wiki.php?action=create&amp;alias=<?=display_str(Wiki::normalize_alias($_GET['search']))?>" class="brackets">Create an article</a>
        </div>
    </div>
    <div>
        <form action="" method="get">
            <div>
                <input type="hidden" name="action" value="search" />
                <input type="hidden" name="nojump" value="1" />
            </div>
            <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
                <tr>
                    <td class="label"><label for="search"><strong>Search for:</strong></label></td>
                    <td colspan="3">
                        <input type="search" name="search" id="search" size="70" value="<?=display_str($_GET['search'])?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Search in:</strong></td>
                    <td>
                        <label><input type="radio" name="type" value="title"<?= ($Type == 'Title') ? ' checked="checked"' : '' ?> /> Title</label>
                        <label><input type="radio" name="type" value="body"<?= ($Type == 'Body') ? ' checked="checked"' : '' ?> /> Body</label>
                    </td>
                    <td class="label"><strong>Order by:</strong></td>
                    <td>
                        <select name="order">
                            <option value="created"<?php Format::selected('order', 'created'); ?>>Created</option>
                            <option value="title"<?php Format::selected('order', 'title'); ?>>Title</option>
                            <option value="edited"<?php Format::selected('order', 'edited'); ?>>Edited</option>
                        </select>
                        <select name="sort">
                            <option value="desc"<?php Format::selected('sort', 'desc'); ?>>Descending</option>
                            <option value="asc"<?php Format::selected('sort', 'asc'); ?>>Ascending</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" class="center">
                        <input type="submit" value="Search" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <br />
<?php
    $Pages = Format::get_pages($Page, $NumResults, ARTICLES_PER_PAGE);
    if ($Pages) { ?>
    <div class="linkbox pager"><?=($Pages)?></div>
<?php
    }

$header = new SortableTableHeader([
    'title'  => 'Article',
    'edited' => 'Last updated on',
], $SortOrder, $OrderWay);
?>
<table width="100%">
    <tr class="colhead">
        <td class="nobr"><?= $header->emit('title', $SortOrderMap['title'][1]) ?></td>
        <td class="nobr"><?= $header->emit('edited', $SortOrderMap['edited'][1]) ?></td>
        <td>Last edited by</td>
    </tr>
<?php
    while (list($ID, $Title, $Date, $UserID) = $DB->next_record()) { ?>
    <tr>
        <td><a href="wiki.php?action=article&amp;id=<?=$ID?>"><?=$Title?></a></td>
        <td><?=$Date?></td>
        <td><?=Users::format_username($UserID, false, false, false)?></td>
    </tr>
<?php } ?>
</table>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php View::show_footer(); ?>
