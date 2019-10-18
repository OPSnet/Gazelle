<?php

if (!$_POST['data'] || empty($_POST['data'])) {
    print('{"status":"error","message":"empty POST"}');
    die();
}

$Data = $_POST['data'];

$GroupFields = [
    'name',
    'recordLabel'
];

foreach ($GroupFields as $GroupField) {
    $Data['response']['group'][$GroupField] = reverse_display_str($Data['response']['group'][$GroupField]);
}

$Tags = count($Data['response']['group']['tags']);
for ($i = 0; $i < $Tags; $i++) {
    $Data['response']['group']['tags'][$i] = reverse_display_str($Data['response']['group']['tags'][$i]);
}

// Artist Name is not escaped for whatever reason, so do not have to try and reverse it

$Data['response']['group']['wikiBody'] = Text::parse_html($Data['response']['group']['wikiBody']);

$TorrentFields = [
    'remasterRecordLabel',
    'description'
];

foreach ($TorrentFields as $TorrentField) {
    $Data['response']['torrent'][$TorrentField] = reverse_display_str($Data['response']['torrent'][$TorrentField]);
}

header('Content-type: application/json');
print(json_encode($Data));
