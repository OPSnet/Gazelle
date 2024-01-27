<?php

$wikiMan = new Gazelle\Manager\Wiki;
if (isset($_GET['id'])) {
    $wiki = $wikiMan->findById((int)$_GET['id']);
} elseif (isset($_GET['name'])) {
    $wiki = $wikiMan->findByAlias(trim($_GET['name']));
} else {
    json_error("bad id");
}

if (is_null($wiki)) {
    json_error("article not found");
}
if ($wiki->minClassRead() > $Viewer->privilege()->effectiveClassLevel()) {
    json_error("higher user class required to view article");
}

Text::$TOC = true;

json_print("success", [
    'title'      => $wiki->title(),
    'bbBody'     => $wiki->body(),
    'body'       => Text::full_format($wiki->body(), false),
    'aliases'    => $wiki->alias(),
    'authorID'   => $wiki->authorId(),
    'authorName' => (new Gazelle\Manager\User)->findById($wiki->authorId())?->username(),
    'date'       => $wiki->date(),
    'revision'   => $wiki->revision(),
]);
