<?php

function getClassObject($name, $db) {
    $name = str_replace("_", "", ucwords($name, "_"));
    require_once(SERVER_ROOT."/sections/api/{$name}.php");
    return new $name($db);
}

$available = array(
    'generate_invite'
);

switch($_GET['action']) {
    case 'generate_invite':
        $class = getClassObject('generate_invite', $DB);
        break;
    default:
        error('invalid action');
}

if (empty($_GET['aid']) || empty($_GET['token'])) {
    error('invalid parameters');
}

$app_id = intval($_GET['aid']);
$user_id = intval($_GET['uid']);
$token = $_GET['token'];

$app = $Cache->get_value("api_apps_{$app_id}");
if (!is_array($app)) {
	$DB->query("
		SELECT Token, Name
		FROM api_applications
		WHERE ID = '{$app_id}'
		LIMIT 1");
	if ($DB->record_count() === 0) {
	    error('invalid app');
    }
    $app = $DB->to_array(false, MYSQLI_ASSOC);
	$Cache->cache_value("api_apps_{$app_id}", $app, 0);
}
$app = $app[0];

if ($app['Token'] !== $token) {
    error('invalid token');
}

$response = $class->run();
print(json_encode(array('status' => 200, 'response' => $response), JSON_UNESCAPED_SLASHES));
//$Debug->profile();
