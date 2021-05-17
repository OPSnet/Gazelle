<?php
/* AJAX Previews, simple stuff. */
Text::$TOC = true;
if (!empty($_POST['AdminComment'])) {
    echo Text::full_format($_POST['AdminComment']);
} elseif (!empty($_POST['WikiText'])) {
    echo Text::full_format($_REQUEST['WikiText']);
} else {
    echo Text::full_format($_REQUEST['body']);
}
