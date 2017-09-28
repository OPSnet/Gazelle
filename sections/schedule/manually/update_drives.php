<?php

// Scrape the Drive database from AccurateRip to get list of drives and their offsets

$DB->query("TRUNCATE drives");

$CH = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://www.accuraterip.com/driveoffsets.htm');
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$Document = new DOMDocument();
$Document->loadHTML(curl_exec($ch));
curl_close($CH);

$Tables = $Document->getElementsByTagName('table');
// we want the second table on this page
$Table = $Tables->item(1);
for ($i = 0; $i < $Table->childNodes->length; $i++) {
	// the first row is a header row
	if ($i === 0) {
		continue;
	}
	$ChildNode = $Table->childNodes->item($i);

	$Name = trim($ChildNode->childNodes->item(0), '- ');
	$Offset = $ChildNode->childNodes->item(1);
	$DB->query("INSERT INTO drives (Name, Offset) VALUES ('".db_string($Name)."', '".db_string($Offset)."')");
}
