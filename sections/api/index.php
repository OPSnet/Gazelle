<?php

$className = "Gazelle\\API\\" . str_replace("_", "", ucwords($_GET['action'], "_"));
if (!class_exists($className)) {
    json_error('invalid action');
}
if (empty($_GET['aid']) || empty($_GET['token'])) {
    json_error('invalid parameters');
}

$api = new $className($Twig, [
    'ReleaseTypes' => (new \Gazelle\ReleaseType)->list(),
    'Debug' => $Debug,
]);

$appId = (int)$_GET['aid'];
$token = $_GET['token'];
$key = "api_applications_{$appId}";

$app = $Cache->get_value($key);
if (!is_array($app)) {
    $app = $DB->rowAssoc("
        SELECT Token, Name
        FROM api_applications
        WHERE ID = ?
        LIMIT 1
        ", $appId
    );
    if (is_null($app)) {
        json_error('invalid app');
    }
    $Cache->cache_value($key, $app, 0);
}

if ($app['Token'] !== $token) {
    json_error('invalid token');
}

$response = $api->run();
print(json_encode(['status' => 200, 'response' => $response], JSON_UNESCAPED_SLASHES));
