<?php

if (!$_POST['html'] || empty($_POST['html'])) {
    print("empty");
    die();
}

print(Text::parse_html($_POST['html']));
