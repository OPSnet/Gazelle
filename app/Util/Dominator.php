<?php

namespace Gazelle\Util;

/* The Dominator class is used to stash bits of dynamic Javascript needed to
 * render the site. At the end of the page layout, all of the accumlated
 * handlers are emitted in a document.ready callback.
 */

class Dominator extends \Gazelle\Base {
    protected static array $click = [];

    public function click(string $id, string $code): string {
        self::$click[$id] = $code;
        return '';
    }

    public function emit(): string {
        $js = "<script type=\"text/javascript\">document.addEventListener('DOMContentLoaded', function() {\n";
        foreach (self::$click as $id => $code) {
            $js .= "\$('$id').click(function () {" . "$code});\n";
        }
        return $js . '})</script>';
    }
}
