<?php

if (isset($Viewer)) {
    header("Location: index.php");
    exit;
}

echo $Twig->render('recovery/index.twig');
