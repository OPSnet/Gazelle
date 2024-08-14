<?php
/** @phpstan-var \Gazelle\User $Viewer */

$notifier = 'Gazelle\\User\\Notification\\' . $_POST['type'];
if (!class_exists($notifier)) {
    json_die('failure', 'no such notification');
}

json_print('success', [
    'clear' => (new $notifier($Viewer))->clear() /** @phpstan-ignore-line */
]);
