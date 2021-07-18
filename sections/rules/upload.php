<?php

[,,$Body] = (new Gazelle\Manager\Wiki)->article(RULES_WIKI_PAGE_ID);
Text::$TOC = true;
$Body = Text::full_format($Body, false, 3, true);
View::show_header('Uploading Rules', ['js' => 'rules']);
?>
<div class="thin">
    <?= $Twig->render('rules/toc.twig') ?>
    <div class="header">
        <h2>Uploading Rules</h2>
    </div>
    <br />
    <form class="search_form" name="rules" onsubmit="return false" action="">
        <input type="text" id="search_string" value="Filter (empty to reset)" />
        <span id="Index">Example: The search term <strong>FLAC</strong> returns
        all rules containing <strong>FLAC</strong>. The search term
        <strong>FLAC+trump</strong> returns all rules containing both
        <strong>FLAC</strong> and <strong>trump</strong>.</span>
    </form>
    <br />
    <div class="before_rules">
        <div class="box pad" style="padding: 10px 10px 10px 20px;">
            <?= Text::parse_toc(0, true) ?>
        </div>
    </div>
    <div id="actual_rules">
        <?= $Body ?>
    </div>
</div>
<?php
View::show_footer();
