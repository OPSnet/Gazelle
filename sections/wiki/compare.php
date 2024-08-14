<?php

//Diff function by Leto of StC.
function diff(string $OldText, string $NewText): array {
    $LineArrayOld = explode("\n", $OldText);
    $LineArrayNew = explode("\n", $NewText);
    $LineOffset = 0;
    $Result = [];

    foreach ($LineArrayOld as $OldLine => $OldString) {
        $Key = $OldLine + $LineOffset;
        if ($Key < 0) {
            $Key = 0;
        }
        $Found = -1;

        while ($Key < count($LineArrayNew)) {
            if ($OldString != $LineArrayNew[$Key]) {
                $Key++;
            } elseif ($OldString == $LineArrayNew[$Key]) {
                $Found = $Key;
                break;
            }
        }

        if ($Found == '-1') { //we never found the old line in the new array
            $Result[] = '<span class="line_deleted">&larr; ' . $OldString . '</span><br />';
            $LineOffset = $LineOffset - 1;
        } elseif ($Found == $OldLine + $LineOffset) {
            $Result[] = '<span class="line_unchanged">&#8597; ' . $OldString . '</span><br />';
        } elseif ($Found != $OldLine + $LineOffset) {
            if ($Found < $OldLine + $LineOffset) {
                $Result[] = '<span class="line_moved">&#8676; ' . $OldString . '</span><br />';
            } else {
                $Result[] = '<span class="line_moved">&larr; ' . $OldString . '</span><br />';
                $Key = $OldLine + $LineOffset;
                while ($Key < $Found) {
                    $Result[] = '<span class="line_new">&rarr; ' . $LineArrayNew[$Key] . '</span><br />';
                    $Key++;
                }
                $Result[] = '<span class="line_moved">&rarr; ' . $OldString . '</span><br />';
            }
                $LineOffset = $Found - $OldLine;
        }
    }
    if (count($LineArrayNew) > count($LineArrayOld) + $LineOffset) {
        $Key = count($LineArrayOld) + $LineOffset;
        while ($Key < count($LineArrayNew)) {
            $Result[] = '<span class="line_new">&rarr; ' . $LineArrayNew[$Key] . '</span><br />';
            $Key++;
        }
    }
    return $Result;
}

/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$old = (int)($_GET['old'] ?? 0);
$new = (int)($_GET['new'] ?? 0);
if ($old >= $new) {
    error("Selected older revision is more recent than selected newer revision.");
}

$wikiMan = new Gazelle\Manager\Wiki();
$article = $wikiMan->findById((int)$_GET['id']);
if (is_null($article)) {
    error(404);
}
if (!$article->readable($Viewer)) {
    error(403);
}

View::show_header("Compare Article Revisions $old versus $new");
echo $Twig->render('wiki/compare.twig', [
    'article' => $article,
    'diff'    => diff($article->revisionBody($old), $article->revisionBody($new)),
    'new'     => $new,
    'old'     => $old,
]);
View::show_footer();

$Diff2 = $article->revisionBody($new);
$Diff1 = $article->revisionBody($old);
