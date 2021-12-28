<?php

Text::$TOC = true;

if (!empty($_POST['admincomment'])) {
    echo Text::full_format($_POST['admincomment']);
} elseif (!empty($_POST['WikiText'])) {
    echo Text::full_format($_REQUEST['WikiText']);
} else {
    echo Text::full_format($_REQUEST['body']);
}
