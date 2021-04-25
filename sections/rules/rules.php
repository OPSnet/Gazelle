<?php
View::show_header('Rule Index');
?>
<div class="thin">
    <?= $Twig->render('rules/toc.twig') ?>
    <div class="header">
        <h2 id="general">Golden Rules</h2>
        <p>The Golden Rules encompass all of <?php echo SITE_NAME; ?> and our IRC Network.
        These rules are paramount; non-compliance will jeopardize your account.</p>
    </div>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
        <?= $Twig->render('rules/golden.twig', []) ?>
    </div>
</div>
<?php
View::show_footer();
