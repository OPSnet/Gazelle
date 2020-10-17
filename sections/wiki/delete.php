<?php
authorize();

if (!check_perms('admin_manage_wiki')) {
    error(403);
}

$ID = (int)$_GET['id'];
if (!$ID) {
    error(404);
}

if ($ID == INDEX_ARTICLE) {
    error('You cannot delete the main wiki article.');
}


if ($DB->scalar("SELECT MinClassEdit FROM wiki_articles WHERE ID = ?", $ArticleID) > $LoggedUser['EffectiveClass']) {
    error(403);
}

$Title = $DB->scalar("
    SELECT Title FROM wiki_articles WHERE ID = ?
    ", $ID
);
if (!$title) {
    error(404);
}

//Log
(new Gazelle\Log)->general("Wiki article $ID ($Title) was deleted by ".$LoggedUser['Username']);
//Delete
$DB->prepared_query("DELETE FROM wiki_articles WHERE ID = ?", $ID);
$DB->prepared_query("DELETE FROM wiki_aliases WHERE ArticleID = ?", $ID);
$DB->prepared_query("DELETE FROM wiki_revisions WHERE ID = ?", $ID);
Wiki::flush_aliases();
Wiki::flush_article($ID);

header("location: wiki.php");
