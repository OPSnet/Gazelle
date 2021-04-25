<?php
View::show_header('Chat Rules');
?>
<div class="thin">
    <?= $Twig->render('rules/toc.twig') ?>
    <div class="box pad" style="padding: 10px 10px 10px 20px;">
        <p>Anything not allowed on the forums is also not allowed on IRC and vice versa. They are separated for convenience only.</p>
    </div>
    <br />

    <h2 id="forums">Forum Rules</h2>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
    <?= $Twig->render('rules/forum.twig') ?>
    </div>

    <h2 id="irc">IRC Rules</h2>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
    <?= $Twig->render('rules/irc.twig') ?>
    </div>
</div>
<?php
View::show_footer();
