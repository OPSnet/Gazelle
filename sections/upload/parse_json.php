<?php

if (!$_POST['data'] || empty($_POST['data'])) {
    print('{"status":"error","message":"empty POST"}');
    die();
}

$Data = $_POST['data'];

$Fields = [
    'name',
    'recordLabel'
];
foreach ($Fields as $Field) {
    if (isset($Data['response']['group'][$Field])) {
        $Data['response']['group'][$Field] = reverse_display_str($Data['response']['group'][$Field]);
    }
}

if (isset($Data['response']['group']['tags'])) {
    $Tags = count($Data['response']['group']['tags']);
    for ($i = 0; $i < $Tags; $i++) {
        $Data['response']['group']['tags'][$i] = reverse_display_str($Data['response']['group']['tags'][$i]);
    }
}


// Artist Name is not escaped for whatever reason, so do not have to try and reverse it
if (isset($Data['response']['group']['wikiBody'])) {
    $Data['response']['group']['wikiBody'] = Text::parse_html($Data['response']['group']['wikiBody']);
}

$Fields = [
    'remasterRecordLabel',
    'description'
];
foreach ($Fields as $Field) {
    if (isset($Data['response']['torrent'][$Field])) {
        $Data['response']['torrent'][$Field] = reverse_display_str($Data['response']['torrent'][$Field]);
    }
}

// Convert these fields back to booleans
$Fields = [
    'remastered',
    'scene',
    'hasLog',
    'hasCue',
    'freeTorrent',
    'reported'
];
foreach ($Fields as $Field) {
    if (isset($Data['response']['torrent'][$Field])) {
        $Data['response']['torrent'][$Field] = strtolower($Data['response']['torrent'][$Field]) === "true";
    }
}

header('Content-type: application/json');
print(json_encode($Data));
