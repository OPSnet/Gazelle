<?php

$privMan = new Gazelle\Manager\Privilege;

View::show_header('Privilege Matrix');
?>
<div class="thin">
    <div class="header">
        <div class="linkbox">
            <a href="tools.php?action=permissions&amp;id=new" class="brackets">Create a new permission set</a>
            <a href="tools.php?action=permissions" class="brackets">Privilege Manager</a>
            <a href="tools.php" class="brackets">Back to tools</a>
        </div>
    </div>
<?= $Twig->render('admin/privilege-matrix.twig', [
    'class_list'     => $privMan->classList(),
    'privilege'      => $privMan->privilege(),
    'star'           => "\xE2\x98\x85",
    'tick'           => ICON_ALL,
]) ?>
</div>
<?php
View::show_footer();
