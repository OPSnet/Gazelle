<?php

$wikiMan = new Gazelle\Manager\Wiki;
if (!empty($_GET['id']) && is_number($_GET['id'])) { //Visiting article via ID
    $ArticleID = (int)$_GET['id'];
} elseif ($_GET['name'] != '') { //Retrieve article ID via alias.
    $ArticleID = $wikiMan->alias($_GET['name']);
} else {
    json_die("failure");
}

if (!$ArticleID) { //No article found
    json_die("failure", "article not found");
}
[$Revision, $Title, $Body, $Read, $Edit, $Date, $AuthorID, $AuthorName, $Aliases] = $wikiMan->article($ArticleID);
if (is_null($Revision)) {
    json_die("failure", "article not found");
}
if ($Read > $Viewer->effectiveClass()) {
    json_die("failure", "higher user class required to view article");
}

Text::$TOC = true;
$TextBody = Text::full_format($Body, false);

json_print("success", [
    'title' => $Title,
    'bbBody' => $Body,
    'body' => $TextBody,
    'aliases' => $Aliases,
    'authorID' => (int)$AuthorID,
    'authorName' => $AuthorName,
    'date' => $Date,
    'revision' => (int)$Revision
]);
