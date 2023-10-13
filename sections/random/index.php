<?php

$object = match ($_REQUEST['action'] ?? '') {
    'artist'  => (new Gazelle\Manager\Artist)->findRandom(),
    'collage' => (new Gazelle\Manager\Collage)->findRandom(),
    default   => (new Gazelle\Manager\TGroup)->findRandom(),
};
if (is_null($object)) {
    error(404); /* only likely to happen on a brand new installation */
}

header("Location: " . $object->location());
