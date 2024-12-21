<?php
/** @phpstan-var \Gazelle\User $Viewer */

$newTopic = randomString(13);
(new Gazelle\User\Notification($Viewer))->setPushTopic($newTopic);
json_print('success', $newTopic);
