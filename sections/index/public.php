<?php

if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}

View::show_header();

echo <<<HTML
<div class="poetry">
<img="https://ptpimg.me/sb5086.png" />
<p>
Orpheus with his lute made trees<br />
And the mountain tops that freeze<br />
Bow themselves when he did sing:<br />
To his music plants and flowers<br />
Ever sprung; as sun and showers<br />
There had made a lasting spring.<br />
</p>
<br />
<p>
Every thing that heard him play,<br />
Even the billows of the sea,<br />
Hung their heads and then lay by.<br />
In sweet music is such art,<br />
Killing care and grief of heart<br />
Fall asleep, or hearing, die.<br />
</p>
</div>
HTML;

View::show_footer();
