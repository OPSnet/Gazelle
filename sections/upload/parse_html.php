<?php
if (!$_POST['html'] || empty($_POST['html'])) {
    error(-1);
}
header('Content-type: text/plain');
// we can assume that everything sent to this endpoint is legacy gazelle html-escaped bbcode
// hence we run html_unescape() on the result
echo html_unescape(Text::parse_html($_POST['html']));
