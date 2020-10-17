<?php
authorize();

$ArticleID = (int)$_POST['article'];
if (!$ArticleID) {
    error(404);
}

if ($DB->scalar("SELECT MinClassEdit FROM wiki_articles WHERE ID = ?", $ArticleID) > $LoggedUser['EffectiveClass']) {
    error(403);
}

$NewAlias = Wiki::normalize_alias($_POST['alias']);
$Dupe = Wiki::alias_to_id($_POST['alias']);

if ($NewAlias != '' && $NewAlias!='addalias' && $Dupe === false) { //Not null, and not dupe
    $DB->prepared_query("
        INSERT INTO wiki_aliases
               (Alias, UserID, ArticleID)
        VALUES (?,     ?,      ?)
        ", $NewAlias, $LoggedUser['ID'], $ArticleID
    );
} else {
    error('The alias you attempted to add was either null or already in the database.');
}

Wiki::flush_aliases();
Wiki::flush_article($ArticleID);
header('Location: wiki.php?action=article&id='.$ArticleID);
