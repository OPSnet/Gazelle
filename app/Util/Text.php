<?php

namespace Gazelle\Util;

class Text {
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=',
            STR_PAD_RIGHT));
    }

    /**
     * Given a String that is composed of HTML, attempt to convert it back
     * into BBCode. Useful when we're trying to deal with the output from
     * some other site's metadata. This also should reverse the HTML encoding
     * that html_escape does.
     */
    public static function parseHtml(string $html): string {
        $dom = new \DOMDocument();
        $dom->loadHTML(stripslashes($html));

        // For any manipulation that we do on the DOM tree, always go in reverse order or
        // else you end up with broken array pointers and missed elements
        $copyNode = function ($origin, $new) {
            for ($i = count($origin->childNodes) - 1; $i >= 0; $i--) {
                if (count($new->childNodes) > 0) {
                    $new->insertBefore($origin->childNodes[$i], $new->childNodes[0]);
                } else {
                    $new->appendChild($origin->childNodes[$i]);
                }
            }
        };

        $elementList = $dom->getElementsByTagName('div');
        for ($i = $elementList->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            if (str_contains($element->getAttribute('style'), 'text-align')) {
                $new = $dom->createElement('align');
                $copyNode($element, $new);
                $new->setAttribute('align', str_replace('text-align: ', '', $element->getAttribute('style')));
                $element->parentNode->replaceChild($new, $element);
            }
        }

        $elementList = $dom->getElementsByTagName('span');
        for ($i = $elementList->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            if (str_contains($element->getAttribute('class'), 'size')) {
                $new = $dom->createElement('size');
                $copyNode($element, $new);
                $new->setAttribute('size', str_replace('size', '', $element->getAttribute('class')));
                $element->parentNode->replaceChild($new, $element);
            } elseif (str_contains($element->getAttribute('style'), 'font-style: italic')) {
                $new = $dom->createElement('italic');
                $copyNode($element, $new);
                $element->parentNode->replaceChild($new, $element);
            } elseif (str_contains($element->getAttribute('style'), 'text-decoration: underline')) {
                $new = $dom->createElement('underline');
                $copyNode($element, $new);
                $element->parentNode->replaceChild($new, $element);
            } elseif (str_contains($element->getAttribute('style'), 'color: ')) {
                $new = $dom->createElement('color');
                $copyNode($element, $new);
                $new->setAttribute('color', str_replace(['color: ', ';'], '', $element->getAttribute('style')));
                $element->parentNode->replaceChild($new, $element);
            } elseif (preg_match("/display:[ ]*inline\-block;[ ]*padding:/", $element->getAttribute('style')) !== false) {
                $new = $dom->createElement('pad');
                $copyNode($element, $new);
                $Padding = explode(' ', trim(explode(':', (explode(';', $element->getAttribute('style'))[1]))[1]));
                $new->setAttribute('pad', implode('|', array_map(fn($x) => rtrim($x, 'px'), $Padding)));
                $element->parentNode->replaceChild($new, $element);
            }
        }

        $elementList = $dom->getElementsByTagName('ul');
        for ($i = 0; $i < $elementList->length; $i++) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            $InnerElements = $element->getElementsByTagName('li');
            for ($j = $InnerElements->length - 1; $j >= 0; $j--) {
                $element = $InnerElements->item($j);
                $new = $dom->createElement('bullet');
                $copyNode($element, $new);
                $element->parentNode->replaceChild($new, $element);
            }
        }

        $elementList = $dom->getElementsByTagName('ol');
        for ($i = 0; $i < $elementList->length; $i++) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            $InnerElements = $element->getElementsByTagName('li');
            for ($j = $InnerElements->length - 1; $j >= 0; $j--) {
                $element = $InnerElements->item($j);
                $new = $dom->createElement('number');
                $copyNode($element, $new);
                $element->parentNode->replaceChild($new, $element);
            }
        }

        $elementList = $dom->getElementsByTagName('strong');
        for ($i = $elementList->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            if (in_array('important_text', explode(' ', $element->getAttribute('class')))) {
                $new = $dom->createElement('important');
                $copyNode($element, $new);
                $element->parentNode->replaceChild($new, $element);
            }
        }

        $elementList = $dom->getElementsByTagName('a');
        for ($i = $elementList->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $element */
            $element = $elementList->item($i);
            if ($element->hasAttribute('href')) {
                $element->removeAttribute('rel');
                $element->removeAttribute('target');
                if ($element->getAttribute('href') === $element->nodeValue) {
                    $element->removeAttribute('href');
                } elseif (
                    $element->getAttribute('href') === 'javascript:void(0);'
                    && $element->getAttribute('onclick') === 'BBCode.spoiler(this);'
                ) {
                    $spoilers = $dom->getElementsByTagName('blockquote');
                    for ($j = $spoilers->length - 1; $j >= 0; $j--) {
                        /** @var \DOMElement $spoiler */
                        $spoiler = $spoilers->item($j);
                        if ($spoiler->hasAttribute('class') && $spoiler->getAttribute('class') === 'hidden spoiler') {
                            $new = $dom->createElement('spoiler');
                            $copyNode($spoiler, $new);
                            $element->parentNode->replaceChild($new, $element);
                            $spoiler->parentNode->removeChild($spoiler);
                            break;
                        }
                    }
                } elseif (str_starts_with($element->getAttribute('href'), 'artist.php?artistname=')) {
                    $new = $dom->createElement('artist');
                    $copyNode($element, $new);
                    $element->parentNode->replaceChild($new, $element);
                } elseif (str_starts_with($element->getAttribute('href'), 'user.php?action=search&search=')) {
                    $new = $dom->createElement('user');
                    $copyNode($element, $new);
                    $element->parentNode->replaceChild($new, $element);
                }
            }
        }

        $Str = (string)$dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        $Str = str_replace(["<body>\n", "\n</body>", "<body>", "</body>"], "", $Str);
        $Str = str_replace(["\r\n", "\n"], "", $Str);
        $Str = preg_replace("/\<strong\>([a-zA-Z0-9 ]+)\<\/strong\>\: \<spoiler\>/", "[spoiler=\\1]", $Str);
        $Str = str_replace("</spoiler>", "[/spoiler]", $Str);
        $Str = preg_replace("/\<strong class=\"quoteheader\"\>(.*)\<\/strong\>(.*)wrote\:(.*?)\<blockquote\>/", "[quote=\\1]", $Str);
        $Str = preg_replace("/\<(\/*)blockquote\>/", "[\\1quote]", $Str);
        $Str = preg_replace("/\<(\/*)strong\>/", "[\\1b]", $Str);
        $Str = preg_replace("/\<(\/*)italic\>/", "[\\1i]", $Str);
        $Str = preg_replace("/\<(\/*)underline\>/", "[\\1u]", $Str);
        $Str = preg_replace("/\<(\/*)important\>/", "[\\1important]", $Str);
        $Str = preg_replace("/\<(\/*)code\>/", "[\\1code]", $Str);
        $Str = preg_replace("/\<(\/*)pre\>/", "[\\1pre]", $Str);
        $Str = preg_replace("/\<color color=\"(.*?)\"\>/", "[color=\\1]", $Str);
        $Str = str_replace("</color>", "[/color]", $Str);
        $Str = preg_replace("/\<pad pad=\"(.*?)\"\>/", "[pad=\\1]", $Str);
        $Str = str_replace("</pad>", "[/pad]", $Str);
        $Str = str_replace(['<number>', '<bullet>'], ['[#]', '[*]'], $Str);
        $Str = str_replace(['</number>', '</bullet>'], '<br />', $Str);
        $Str = str_replace(['<ul class="postlist">', '<ol class="postlist">', '</ul>', '</ol>'], '', $Str);
        $Str = preg_replace("/\<align align=\"([a-z]+);\">/", "[align=\\1]", $Str);
        $Str = str_replace("</align>", "[/align]", $Str);
        $Str = preg_replace("/\<size size=\"([0-9]+)\"\>/", "[size=\\1]", $Str);
        $Str = str_replace("</size>", "[/size]", $Str);
        $Str = preg_replace('#/torrents.php\?recordlabel="?(?:[^"]*)#', SITE_URL . '\\0', $Str);
        $Str = preg_replace('#/torrents.php\?taglist="?(?:[^"]*)#', SITE_URL . '\\0', $Str);
        $Str = preg_replace("/\<(\/*)artist>/", "[\\1artist]", $Str);
        $Str = preg_replace("/\<(\/*)user>/", "[\\1user]", $Str);
        $Str = preg_replace("/\<a href=\"([^\"]*?)\">/", "[url=\\1]", $Str);
        $Str = preg_replace("/\<(\/*)a\>/", "[\\1url]", $Str);
        $Str = preg_replace("/\<img(.*?)src=\"(.*?)\"(.*?)\>/", '[img]\\2[/img]', $Str);
        $Str = str_replace('<p>', '', $Str);
        $Str = str_replace('</p>', '<br />', $Str);
        return str_replace(["<br />", "<br>"], "\n", $Str);
    }
}
