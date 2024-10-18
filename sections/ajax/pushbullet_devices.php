<?php

if (!isset($_GET['apikey']) || empty($_GET['apikey'])) {
    echo '{"error": { "message": "No API Key specified" }}';
    die();
}

$ApiKey = $_GET['apikey'];

$Ch = curl_init();
if ($Ch === false) {
    echo '[]';
    exit;
}
curl_setopt_array($Ch, [
    CURLOPT_URL => 'https://api.pushbullet.com/api/devices',
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $ApiKey . ':'
]);

$Result = curl_exec($Ch);
curl_close($Ch);
echo json_encode($Result);
