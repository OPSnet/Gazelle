<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

switch ($_GET['p'] ?? '') {
    case 'chat':
        echo $Twig->render('rules/chat.twig');
        break;
    case 'clients':
        echo $Twig->render('rules/client-whitelist.twig', [
            'list' => (new Gazelle\Manager\ClientWhitelist())->list(),
        ]);
        break;
    case 'collages':
        echo $Twig->render('rules/collage.twig');
        break;
    case 'ratio':
        $b   = $Viewer->downloadedSize();
        $GiB = 1024 * 1024 * 1024;
        echo $Twig->render('rules/ratio.twig', [
            'level_1'  => ($b <    5 * $GiB) ? 'a' : 'b',
            'level_2'  => ($b >=   5 * $GiB && $b <  10 * $GiB) ? 'a' : 'b',
            'level_3'  => ($b >=  10 * $GiB && $b <  20 * $GiB) ? 'a' : 'b',
            'level_4'  => ($b >=  20 * $GiB && $b <  30 * $GiB) ? 'a' : 'b',
            'level_5'  => ($b >=  30 * $GiB && $b <  40 * $GiB) ? 'a' : 'b',
            'level_6'  => ($b >=  40 * $GiB && $b <  50 * $GiB) ? 'a' : 'b',
            'level_7'  => ($b >=  50 * $GiB && $b <  60 * $GiB) ? 'a' : 'b',
            'level_8'  => ($b >=  60 * $GiB && $b <  80 * $GiB) ? 'a' : 'b',
            'level_9'  => ($b >=  80 * $GiB && $b < 100 * $GiB) ? 'a' : 'b',
            'level_10' => ($b >= 100 * $GiB) ? 'a' : 'b',
        ]);
        break;
    case 'requests':
        echo $Twig->render('rules/request.twig');
        break;
    case 'tag':
        echo $Twig->render('rules/tag-page.twig');
        break;
    case 'upload':
        Text::$TOC = true;
        echo $Twig->render('rules/upload.twig', [
            'body' => Text::full_format((new Gazelle\Wiki(RULES_WIKI_PAGE_ID))->body(), false, 3, true),
            'toc'  => Text::parse_toc(0, true),
        ]);
        break;
    default:
        echo $Twig->render('rules/index.twig');
        break;
}
