<?php

declare(strict_types=1);

namespace Gazelle;

\Text::$TOC = true;

if (!empty($_POST['admincomment'])) {
    echo \Text::full_format($_POST['admincomment'], cache: Enum\ImageCacheMode::passthru);
} elseif (!empty($_POST['WikiText'])) {
    echo \Text::full_format($_REQUEST['WikiText'], cache: Enum\ImageCacheMode::passthru);
} else {
    echo \Text::full_format($_REQUEST['body'], cache: Enum\ImageCacheMode::passthru);
}
