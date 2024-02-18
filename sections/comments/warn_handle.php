<?php

require_once(__DIR__ . '/../forums/do_warn.php');

[$post, $body] = handleWarningRequest(new Gazelle\Manager\Comment());
$post->setField('Body', $body)
    ->setField('EditedUserID', $Viewer->id())->modify();
