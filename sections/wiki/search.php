<?php

if (empty($_GET['nojump'])) {
    $ArticleID = Wiki::alias_to_id($_GET['search']);
    if ($ArticleID) {
        //Found the article!
        header("Location: wiki.php?action=article&id={$ArticleID}");
        die();
    }
}

$header = new \Gazelle\Util\SortableTableHeader('created', [
    'created' => ['dbColumn' => 'ID',    'defaultSort' => 'desc'],
    'title'   => ['dbColumn' => 'Title', 'defaultSort' => 'asc',  'text' => 'Article'],
    'edited'  => ['dbColumn' => 'Date',  'defaultSort' => 'desc', 'text' => 'Last updated on'],
]);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();

$TypeMap = [
    'title' => 'Title',
    'body'  => 'Body',
];
$Type = $TypeMap[$_GET['type'] ?? 'title'];

// Break search string down into individual words
$words = explode(' ', trim($_GET['search']));
$cond = array_fill(0, count($words), "$Type LIKE concat('%', ?, '%')");
$args = $words;
$cond[] = 'MinClassRead <= ?';
$args[] = $LoggedUser['EffectiveClass'];
$where = 'WHERE ' . implode(' AND ', $cond);

$NumResults = $DB->scalar("
    SELECT count(*) FROM wiki_articles $where
    ", ...$args
);

View::show_header('Search articles');
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
    [$Page, $Limit] = Format::page_limit(WIKI_ARTICLES_PER_PAGE);
    $Pages = Format::get_pages($Page, $NumResults, WIKI_ARTICLES_PER_PAGE);
    if ($Pages) {
?>
    <div class="linkbox pager"><?= $Pages ?></div>
<?php } ?>
<table width="100%">
    <tr class="colhead">
        <td class="nobr"><?= $header->emit('title') ?></td>
        <td class="nobr"><?= $header->emit('edited') ?></td>
        <td>Last edited by</td>
    </tr>
<?php
$DB->prepared_query("
    SELECT ID,
        Title,
        Date,
        Author
    FROM wiki_articles
    $where
    ORDER BY $OrderBy $OrderDir
    LIMIT $Limit
    ", ...$args
);
while ([$ID, $Title, $Date, $UserID] = $DB->next_record()) {
?>
    <tr>
        <td><a href="wiki.php?action=article&amp;id=<?=$ID?>"><?=$Title?></a></td>
        <td><?=$Date?></td>
        <td><?=Users::format_username($UserID, false, false, false)?></td>
    </tr>
<?php } ?>
</table>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
View::show_footer();
