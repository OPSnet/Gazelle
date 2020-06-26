<?php
Text::$TOC = true;

$Article = Wiki::get_article(RULES_WIKI)[0]['Body'];

$Body = Text::full_format($Article, false, 3, true);
$TOC = Text::parse_toc(0, true);

View::show_header('Uploading Rules', 'rules');
?>
<div class="thin">
    <?= G::$Twig->render('rules/toc.twig') ?>
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
            <?= $TOC ?>
        </div>
    </div>
    <div id="actual_rules">
        <?= $Body ?>
    </div>
</div>
<?php
View::show_footer();
