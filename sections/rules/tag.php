<?php
View::show_header('Tagging rules');
?>
<div class="thin">
<?php include('jump.php'); ?>
    <div class="header">
        <h2 id="general">Tagging rules</h2>
    </div>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
        <?= $Twig->render('rules/tag.twig', ['on_upload' => false]) ?>
    </div>
</div>
<?php
View::show_footer();
