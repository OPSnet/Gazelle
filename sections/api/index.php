<?php
/** @phpstan-var \Gazelle\Debug $Debug */

if (empty($_GET['aid']) || empty($_GET['token'])) {
    json_error('invalid parameters');
}
if (!(new Gazelle\API())->validateToken((int)($_GET['aid'] ?? 0), $_GET['token'] ?? '')) {
    json_error('invalid token');
}
$className = "Gazelle\\API\\" . str_replace("_", "", ucwords($_GET['action'], "_"));
if (!class_exists($className)) {
    json_error('invalid action');
}

$api = new $className([
    'ReleaseTypes' => (new \Gazelle\ReleaseType())->list(),
    'Debug'        => $Debug,
]);

print(json_encode(
    [
        'status'   => 200,
        'response' => $api->run(),
    ],
    JSON_UNESCAPED_SLASHES
));
