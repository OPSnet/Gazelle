<?php

$title = 'Browse wiki articles';

$sql = "
    SELECT ID,
        Title,
        Date,
        Author
    FROM wiki_articles
    WHERE MinClassRead <= ?
    ";
$args = [$LoggedUser['EffectiveClass']];
if (!empty($_GET['letter'])) {
    $letter = strtoupper(substr($_GET['letter'], 0, 1));
    if ($letter !== '1') {
        $title .= ' ('.$letter.')';
        $sql .= " AND LEFT(Title,1) = ?";
        $args[] = $letter;
    }
}

$sql .= " ORDER BY Title";

$DB->prepared_query($sql, ...$args);

View::show_header($title);

echo G::$Twig->render('wiki/browse.twig', [
    'title' => $title,
    'articles' => G::$DB->to_array(false, MYSQLI_ASSOC),
]);

View::show_footer();
