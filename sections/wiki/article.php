<?php

$wikiMan = new Gazelle\Manager\Wiki;
$ClassLevels = (new Gazelle\Manager\User)->classLevelList();

Text::$TOC = true;
$ArticleID = false;
if (!empty($_GET['id']) && is_number($_GET['id'])) { //Visiting article via ID
    $ArticleID = (int)$_GET['id'];
} elseif ($_GET['name'] !== '') { //Retrieve article ID via alias.
    $ArticleID = $wikiMan->alias($_GET['name']);
} else { //No ID, No Name
    error('Unknown article ['.display_str($_GET['id']).']');
}

if (!$ArticleID) { //No article found
    View::show_header('No article found');
?>
<div class="thin">
    <div class="header">
        <h2>No article found</h2>
    </div>
    <div class="box pad" style="padding: 10px 10px 10px 20px;">
        There is no article matching the name you requested.
        <ul>
            <li><a href="wiki.php?action=search&amp;search=<?=display_str($_GET['name'])?>">Search</a> for an article similar to this.</li>
            <li><a href="wiki.php?action=create&amp;alias=<?=display_str($wikiMan->normalizeAlias($_GET['name']))?>">Create</a> an article in its place.</li>
        </ul>
    </div>
</div>
<?php
    View::show_footer();
    die();
}

[$Revision, $Title, $Body, $Read, $Edit, $Date, $AuthorID, $Aliases, $UserIDs] = $wikiMan->article($ArticleID);
if ($Read > $Viewer->effectiveClass()) {
    error('You must be a higher user class to view this wiki article');
}

$TextBody = Text::full_format($Body, false);
$TOC = Text::parse_toc(0);

View::show_header($Title, ['js' => 'wiki,bbcode']);
?>
<div class="thin">
    <div class="header">
        <h2><a href="wiki.php">Wiki</a> &rsaquo; <?=$Title?></h2>
        <div class="linkbox">
            <a href="wiki.php?action=browse" class="brackets">Browse</a>
            <a href="wiki.php?action=create" class="brackets">Create</a>
<?php if ($Edit <= $Viewer->effectiveClass()) { ?>
            <a href="wiki.php?action=edit&amp;id=<?=$ArticleID?>" class="brackets">Edit</a>
<?php } ?>
            <a href="wiki.php?action=revisions&amp;id=<?=$ArticleID?>" class="brackets">History</a>
<?php if (check_perms('admin_manage_wiki') && $_GET['id'] != INDEX_WIKI_PAGE_ID) { ?>
            <a href="wiki.php?action=delete&amp;id=<?=$ArticleID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets" onclick="return confirm('Are you sure you want to delete?\nYes, DELETE, not as in \'Oh hey, if this is wrong we can get someone to magically undelete it for us later\' it will be GONE.\nGiven this new information, do you still want to DELETE this article and all its revisions and all its aliases and act like it never existed?')">Delete</a>
<?php } ?>
        </div>
    </div>
    <div class="sidebar">
        <div class="box">
            <div class="head">Search</div>
            <div class="pad">
            <form class="search_form" name="articles" action="wiki.php" method="get">
                <input type="hidden" name="action" value="search" />
                <input type="search" placeholder="Search articles" name="search" size="20" />
                <input value="Search" type="submit" class="hidden" />
            </form>
        </div>
        </div>
        <div class="box">
            <div class="head">Table of Contents</div>
            <div class="body">
                <?=$TOC?>
            </div>
        </div>
        <div class="box box_info pad">
            <ul>
                <li>
                    <strong>Protection:</strong>
                    <ul>
                        <li>Read: <?=$ClassLevels[$Read]['Name']?></li>
                        <li>Edit: <?=$ClassLevels[$Edit]['Name']?></li>
                    </ul>
                 </li>
                <li>
                    <strong>Details:</strong>
                    <ul>
                        <li>Version: r<?=$Revision?></li>
                        <li>Last edited by: <?=Users::format_username($AuthorID, false, false, false)?></li>
                        <li>Last updated: <?=time_diff($Date)?></li>
                    </ul>
                </li>
                <li>
                    <strong>Aliases:</strong>
                    <ul>
<?php
if ($Aliases != $Title) {
    $AliasArray = explode(',', $Aliases);
    $UserArray = explode(',', $UserIDs);
    $i = 0;
    foreach ($AliasArray as $AliasItem) {
?>
                        <li id="alias_<?=$AliasItem?>"><a href="wiki.php?action=article&amp;name=<?=$AliasItem?>"><?=shortenString($AliasItem, 20, true)?></a><?php if (check_perms('admin_manage_wiki')) { ?> <a href="#" onclick="Remove_Alias('<?=$AliasItem?>'); return false;" class="brackets tooltip" title="Delete alias">X</a> <a href="user.php?id=<?=$UserArray[$i]?>" class="brackets tooltip" title="View user">U</a><?php } ?></li>
<?php        $i++;
    }
}
?>
                    </ul>
                </li>
            </ul>
        </div>
<?php if ($Edit <= $Viewer->effectiveClass()) { ?>
        <div class="box box_addalias">
            <div style="padding: 5px;">
                <form class="add_form" name="aliases" action="wiki.php" method="post">
                    <input type="hidden" name="action" value="add_alias" />
                    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                    <input type="hidden" name="article" value="<?=$ArticleID?>" />
                    <input
                        onfocus="if (this.value == 'Add alias') this.value='';"
                        onblur="if (this.value == '') this.value='Add alias';"
                        value="Add alias" type="text" name="alias" size="20"
                    />
                    <input type="submit" value="+" />
                </form>
            </div>
        </div>
<?php } ?>
    </div>
    <div class="main_column">
    <div class="box wiki_article">
        <div class="pad"><?=$TextBody?></div>
    </div>
    </div>
</div>
<?php
View::show_footer();
