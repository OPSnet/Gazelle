<?php

if (!$_POST['html'] || empty($_POST['html'])) {
	print("empty");
	var_dump($_POST);
	die();
}

print(Text::parse_html($_POST['html']));