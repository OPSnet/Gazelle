<?php

echo $Twig->render('user/locked.twig', [
    'viewer' => $Viewer,
]);
