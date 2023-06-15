<?php

echo $Twig->render('chat/index.twig', [
    'user' => $Viewer,
]);
