<?php
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use Gazelle\Enum\CacheBucket;

class Text {
    /**
     * Array of valid tags; tag => max number of attributes
     */
    private static array $ValidTags = [
        '###'        => 1,
        '##'         => 1,
        '#'          => 1,
        '*'          => 1,
        '**'         => 1,
        '***'        => 1,
        'align'      => 1,
        'artist'     => 0,
        'b'          => 0,
        'box'        => 0,
        'code'       => 1,
        'collage'    => 1,
        'color'      => 1,
        'colour'     => 1,
        'forum'      => 0,
        'headline'   => 1,
        'hide'       => 1,
        'hr'         => 0,
        'i'          => 0,
        'img'        => 1,
        'important'  => 0,
        'inlinesize' => 1,
        'inlineurl'  => 0,
        'mature'     => 1,
        'n'          => 0,
        'pad'        => 1,
        'pl'         => 1,
        'plain'      => 0,
        'pre'        => 1,
        'quote'      => 1,
        'rule'       => 0,
        's'          => 0,
        'size'       => 1,
        'spoiler'    => 1,
        'tex'        => 0,
        'thread'     => 0,
        'torrent'    => 1,
        'u'          => 0,
        'url'        => 1,
        'user'       => 0,
    ];

    /**
     * Array of smilies; code => image file in STATIC_SERVER/common/smileys
     */
    private static array $Smileys = [
        '&gt;.&gt;'  => 'eyesright.gif',
        '&lt;3'      => 'heart.gif',
        ':&#39;('    => 'crying.gif',
        ':('         => 'sad.gif',
        ':)'         => 'smile.gif',
        ':-('        => 'sad.gif',
        ':-)'        => 'smile.gif',
        ':-D'        => 'biggrin.gif',
        ':-P'        => 'tongue.gif',
        ':-p'        => 'tongue.gif',
        ':-|'        => 'blank.gif',
        ':D'         => 'biggrin.gif',
        ':O'         => 'ohshit.gif',
        ':P'         => 'tongue.gif',
        ':angry:'    => 'angry.gif',
        ':blush:'    => 'blush.gif',
        ':cool:'     => 'cool.gif',
        ':creepy:'   => 'creepy.gif',
        ':cry:'      => 'crying.gif',
        ':crying:'   => 'crying.gif',
        ':flaclove:' => 'loveflac.gif',
        ':frown:'    => 'frown.gif',
        ':frowning:' => 'frown.gif',
        ':lol:'      => 'laughing.gif',
        ':loveflac:' => 'loveflac.gif',
        ':ninja:'    => 'ninja.gif',
        ':no:'       => 'no.gif',
        ':nod:'      => 'nod.gif',
        ':o'         => 'ohshit.gif',
        ':ohno:'     => 'ohnoes.gif',
        ':ohnoes:'   => 'ohnoes.gif',
        ':omg:'      => 'omg.gif',
        ':p'         => 'tongue.gif',
        ':paddle:'   => 'paddle.gif',
        ':shifty:'   => 'shifty.gif',
        ':sick:'     => 'sick.gif',
        ':sorry:'    => 'sorry.gif',
        ':thanks:'   => 'thanks.gif',
        ':unsure:'   => 'hmm.gif',
        ':wave:'     => 'wave.gif',
        ':whatlove:' => 'ilu.gif',
        ':wink:'     => 'wink.gif',
        ':worried:'  => 'worried.gif',
        ':wtf:'      => 'wtf.gif',
        ':wub:'      => 'wub.gif',
        ':|'         => 'blank.gif',
        ';-)'        => 'wink.gif',
        ':\\'        => 'hmm.gif',
        ':/'         => 'hmm.gif',
    ];

    private static array $ColorName = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque',
        'black', 'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood',
        'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk',
        'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray',
        'darkgreen', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange',
        'darkorchid', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue',
        'darkslategray', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue',
        'dimgray', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia',
        'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow',
        'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
        'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral',
        'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightpink',
        'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray',
        'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta',
        'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
        'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise',
        'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin',
        'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered',
        'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
        'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple',
        'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
        'seagreen', 'seashell', 'sienna', 'silver', 'skyblue', 'slateblue', 'slategray',
        'snow', 'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato',
        'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen',
    ];

    /**
     * Processed version of the $Smileys array, see {@link smileys}
     */
    private static array $ProcessedSmileys = [];

    /**
     * Whether or not to turn images into URLs (used inside [quote] tags).
     * This is an integer reflecting the number of levels we're doing that
     * transition, i.e. images will only be displayed as images if $NoImg <= 0.
     * By setting this variable to a negative number you can delay the
     * transition to a deeper level of quotes.
     */
    private static int $NoImg = 0;

    /**
     * Internal counter for the level of recursion in to_html
     */
    private static int $Levels = 0;

    /**
     * The maximum amount of nesting allowed (exclusive)
     * In reality n-1 nests are shown.
     */
    private static int $MaximumNests = 10;

    /**
     * Used to detect and disable parsing (e.g. TOC) within quotes
     */
    private static int $InQuotes = 0;

    /**
     * Used to [hide] quote trains starting with the specified depth (inclusive)
     *
     * This defaulted to 5 but was raised to 10 to effectively "disable" it until
     * an optimal number of nested [quote] tags is chosen. The variable $MaximumNests
     * effectively overrides this variable, if $MaximumNests is less than the value
     * of $NestsBeforeHide.
     */
    private static int $NestsBeforeHide = 10;

    /**
     * Array of headlines for Table Of Contents (TOC)
     */
    private static array $Headlines = [];

    /**
     * Counter for making headline URLs unique
     */
    private static int $HeadlineID = 0;

    /**
     * Depth
     */
    private static array $HeadlineLevels = ['1', '2', '3', '4'];

    /**
     * TOC enabler
     */
    public static bool $TOC = false;

    // Sometimes we need a viewer context to determine how to render something.
    // This will be configured correctly for the website, but it can be set up
    // manually in boris is you need to render something that requires knowledge
    // of a user context. But most of the time you can forget about this.
    private static \Gazelle\User $viewer;

    public static function setViewer(\Gazelle\User $viewer): void {
        self::$viewer = $viewer;
    }

    /**
     * Render BBCode as HTML without any div-like elements
     */
    public static function span_format(string $text): string {
        // neuter <br> and self-closing elements elements
        $text = preg_replace('/(?:\n+|\[\s*hr[^]]*\])/i', ' ', $text);
        // neuter [quote]
        $text = preg_replace('/\[\s*\/?quote[^]]*\]/i', '', $text);
        // and strip the rest
        return strip_tags(
            self::full_format($text, false),
            [
                'b',
                'cite',
                'em',
                'i',
                'span',
                'strong',
            ]
        );
    }

    /**
     * Output BBCode as XHTML
     */
    public static function full_format(
        ?string     $Str,
        bool        $OutputTOC = true,
        int         $Min = 3,
        bool        $Rules = false,
        bool        $cache = false,
        CacheBucket $bucket = CacheBucket::standard,
    ): string {
        if (is_null($Str)) {
            return '';
        }
        $Str = html_escape($Str);

        self::$Headlines = [];

        //Inline links
        $URLPrefix = '(\[url\]|\[url\=|\[img\=|\[img\])';
        $Str = preg_replace('/' . $URLPrefix . '\s+/i', '$1', $Str);
        $Str = preg_replace('/(?<!' . $URLPrefix . ')http(s)?:\/\//i', '$1[inlineurl]http$2://', $Str);
        // For anonym.to and archive.org links, remove any [inlineurl] in the middle of the link
        $Str = preg_replace_callback(
            '/(?<=\[inlineurl\]|' . $URLPrefix . ')(\S*\[inlineurl\]\S*)/m',
            fn($m) => str_replace('[inlineurl]', '', $m[0]),
            $Str,
        );

        if (self::$TOC) {
            $Str = preg_replace('/(\={5})([^=].*)\1/i', '[headline=4]$2[/headline]', $Str);
            $Str = preg_replace('/(\={4})([^=].*)\1/i', '[headline=3]$2[/headline]', $Str);
            $Str = preg_replace('/(\={3})([^=].*)\1/i', '[headline=2]$2[/headline]', $Str);
            $Str = preg_replace('/(\={2})([^=].*)\1/i', '[headline=1]$2[/headline]', $Str);
        } else {
            $Str = preg_replace('/(\={4})([^=].*)\1/i', '[inlinesize=3]$2[/inlinesize]', $Str);
            $Str = preg_replace('/(\={3})([^=].*)\1/i', '[inlinesize=5]$2[/inlinesize]', $Str);
            $Str = preg_replace('/(\={2})([^=].*)\1/i', '[inlinesize=7]$2[/inlinesize]', $Str);
        }

        $HTML = nl2br(self::to_html(self::parse($Str), $Rules, $cache, $bucket));

        if (self::$TOC && $OutputTOC) {
            $HTML = self::parse_toc($Min) . $HTML;
        }
        return $HTML;
    }

    public static function strip_bbcode(string $Str): string {
        $Str = html_escape($Str);

        //Inline links
        $Str = preg_replace('/(?<!(\[url\]|\[url\=|\[img\=|\[img\]))http(s)?:\/\//i', '$1[inlineurl]http$2://', $Str);

        return nl2br(self::raw_text(self::parse($Str)));
    }

    private static function valid_url(string $Str, string $Extension = '', bool $Inline = false): int|false {
        $re = '/^'
            . '(https?|ftps?|irc):\/\/' // protocol
            . '(\w+(:\w+)?@)?' // user:pass@
            . '('
                . '(([0-9]{1,3}\.){3}[0-9]{1,3})|' // IP or...
                . '(localhost(\:[0-9]{1,5})?)|' // locahost or...
                . '(([a-z0-9\-\_]+\.)+\w{2,6})' // sub.sub.sub.host.com
            . ')'
            . '(:[0-9]{1,5})?' // port
            . '\/?' // slash?
            . '(\/?[0-9a-z\-_.,&=@~%\/:;()+|!#]+)*' // /file
            . (empty($Extension) ? '' : $Extension)
            . ($Inline
                ? '(\?([0-9a-z\-_.,%\/\@~&=:;()+*\^$!#|?]|\[\d*\])*)?'
                : '(\?[0-9a-z\-_.,%\/\@[\]~&=:;()+*\^$!#|?]*)?')
            . '(#[a-z0-9\-_.,%\/\@[\]~&=:;()+*\^$!]*)?' // #anchor
            . '$/i';
        return preg_match($re, $Str, $Matches);
    }

    private static function relative_url(string $str): bool {
        return !preg_match('~^https?://~', $str);
    }

    public static function local_url(string $Str): string|false {
        $URLInfo = parse_url($Str);
        if (!$URLInfo) {
            return false;
        }
        $Host = $URLInfo['host'];
        if (empty($URLInfo['port']) && in_array($Host, [SITE_HOST, ALT_SITE_HOST])) {
            $URL = '';
            if (!empty($URLInfo['path'])) {
                $URL .= ltrim($URLInfo['path'], '/'); // Things break if the path starts with '//'
            }
            if (!empty($URLInfo['query'])) {
                $URL .= "?{$URLInfo['query']}";
            }
            if (!empty($URLInfo['fragment'])) {
                $URL .= "#{$URLInfo['fragment']}";
            }
            return $URL ? "/$URL" : false;
        } else {
            return false;
        }
    }

    public static function resolve_url(string $url): ?string {
        $rawurl = str_replace('&amp;', '&', $url); // unfuck aggressive escaping
        $info = parse_url($rawurl);
        if (
            !$info
            || empty($info['host'])
            || !in_array($info['host'], [SITE_HOST, ALT_SITE_HOST])
        ) {
            return null;
        }
        parse_str($info['query'] ?? '', $args);

        if (
            isset($args['postid'])
            && isset($info['path'])
            && in_array(
                $info['path'],
                ['/artist.php', '/collages.php', '/requests.php', '/torrents.php']
            )
        ) {
            return self::bbcodeCommentUrl((int)$args['postid']);
        }

        switch ($info['path'] ?? '') {
            case '/artist.php':
                return (new \Gazelle\Manager\Artist())->findById((int)($args['id'] ?? 0))?->link();

            case '/collages.php':
                return self::bbcodeCollageUrl((int)($args['id'] ?? $args['collageid']));

            case '/forums.php':
                return match ($args['action'] ?? '') {
                    'viewforum' => self::bbcodeForumUrl((int)$args['forumid']),
                    'viewthread' => (function ($args) {
                        if (isset($args['postid'])) {
                            return self::bbcodePostUrl((int)$args['postid']);
                        } elseif (isset($args['threadid'])) {
                            return self::bbcodeThreadUrl((int)$args['threadid']);
                        }
                        return null;
                    })($args),
                    default => null,
                };

            case '/torrents.php':
                if (isset($args['torrentid'])) {
                    return (new \Gazelle\Manager\Torrent())->findById((int)$args['torrentid'])?->link();
                } elseif (isset($args['id'])) {
                    return (new \Gazelle\Manager\TGroup())->findById((int)$args['id'])?->link();
                }
                return null;

            case '/wiki.php':
                if (($args['action'] ?? '') === 'article') {
                    $wikiMan = new \Gazelle\Manager\Wiki();
                    $article = null;
                    if (isset($args['id'])) {
                        $article = $wikiMan->findById((int)$args['id']);
                    } elseif (is_string($args['name'])) {
                        $article = $wikiMan->findByAlias($args['name']);
                    }
                    if ($article?->readable(self::$viewer)) {
                        return "<a href=\"wiki.php\">Wiki</a> › {$article->link()}";
                    }
                }
                return null;

            default:
                return null;
        }
    }

    /*
    How parsing works

    Parsing takes $Str, breaks it into blocks, and builds it into $Array.
    Blocks start at the beginning of $Str, when the parser encounters a [, and after a tag has been closed.
    This is all done in a loop.

    EXPLANATION OF PARSER LOGIC

    1) Find the next tag (regex)
        1a) If there aren't any tags left, write everything remaining to a block and return (done parsing)
        1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
    2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
    3) If it's not a wiki link:
        3a) check it against the self::$ValidTags array to see if it's actually a tag and not [bullshit]
            If it's [not a tag], just leave it as plaintext and move on
        3b) Get the attribute, if it exists [name=attribute]
    4) Move the pointer past the end of the tag
    5) Find out where the tag closes (beginning of [/tag])
        5a) Different for different types of tag. Some tags don't close, others are weird like [*]
        5b) If it's a normal tag, it may have versions of itself nested inside - e.g.:
            [quote=bob]*
                [quote=joe]I am a redneck!**[/quote]
                Me too!
            ***[/quote]
        If we're at the position *, the first [/quote] tag is denoted by **.
        However, our quote tag doesn't actually close there. We must perform
        a loop which checks the number of opening [quote] tags, and make sure
        they are all closed before we find our final [/quote] tag (***).

        5c) Get the contents between [open] and [/close] and call it the block.
        In many cases, this will be parsed itself later on, in a new parse() call.
        5d) Move the pointer past the end of the [/close] tag.
    6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
        In many cases, the block may be parsed here itself. Stick them in the $Array.
    7) Increment array pointer, start again (past the end of the [/close] tag)

    */
    public static function parse(string $Str, string $ListPrefix = ''): array {
        // Deal with list markup that is not list markup (e.g. [*] at EOL)
        // such that "abc [*]" => "abc [[n]*]"
        $Str = preg_replace('/(?<!\n\[)(?<!^\[)([*#])(?=\])/', '[n]\\1', $Str);
        $i = 0; // Pointer to keep track of where we are in $Str
        $Len = strlen($Str);
        $Array = [];
        $ArrayPos = 0;
        $StrLC = strtolower($Str);
        $ListId = 1;
        $MaxAttribs = 0;
        $wikiMan = new \Gazelle\Manager\Wiki();

        while ($i < $Len) {
            $Block = '';

            // 1) Find the next tag (regex)
            // [name(=attribute)?]|[[wiki-link]]
            $IsTag = preg_match("/((\[[a-zA-Z*#]+)(=(?:[^\n'\"\[\]]|\[\d*\])+)?\])|(\[\[[^\n\"'\[\]]+\]\])/", $Str, $Tag, PREG_OFFSET_CAPTURE, $i);

            // 1a) If there aren't any tags left, write everything remaining to a block
            if (!$IsTag) {
                // No more tags
                $Array[$ArrayPos] = substr($Str, $i);
                break;
            }

            // 1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
            $TagPos = $Tag[0][1];
            if ($TagPos > $i) {
                $Array[$ArrayPos] = substr($Str, $i, $TagPos - $i);
                ++$ArrayPos;
                $i = $TagPos;
            }

            // 2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
            if (!empty($Tag[4][0])) { // Wiki-link
                $WikiLink = true;
                $alias = substr($Tag[4][0], 2, -2);
                $article = $wikiMan->findByAlias($alias);
                $TagName = urlencode($alias);
                $Attrib = ($article instanceof \Gazelle\Wiki) ? $article : urlencode($alias);
            } else { // 3) If it's not a wiki link:
                $WikiLink = false;
                $TagName = strtolower(substr($Tag[2][0], 1));

                //3a) check it against the self::$ValidTags array to see if it's actually a tag and not [bullshit]
                if (!isset(self::$ValidTags[$TagName])) {
                    $Array[$ArrayPos] = substr($Str, $i, ($TagPos - $i) + strlen($Tag[0][0]));
                    $i = $TagPos + strlen($Tag[0][0]);
                    ++$ArrayPos;
                    continue;
                }

                $MaxAttribs = self::$ValidTags[$TagName];

                // 3b) Get the attribute, if it exists [name=attribute]
                if (!empty($Tag[3][0])) {
                    $Attrib = substr($Tag[3][0], 1);
                } else {
                    $Attrib = '';
                }
            }

            // 4) Move the pointer past the end of the tag
            $i = $TagPos + strlen($Tag[0][0]);

            // 5) Find out where the tag closes (beginning of [/tag])

            // Unfortunately, BBCode doesn't have nice standards like XHTML
            // [*], [img=...], and http:// follow different formats
            // Thus, we have to handle these before we handle the majority of tags

            //5a) Different for different types of tag. Some tags don't close, others are weird like [*]
            // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf
            // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedElseif
            if ($i >= strlen($Str)) {
                // document end, tag unclosed
            } elseif ($TagName == 'img' && !empty($Tag[3][0])) { //[img=...]
                $Block = ''; // Nothing inside this tag
                // Don't need to touch $i
            } elseif ($TagName == 'hr') {
                $Block = ''; // Nothing inside this tag either
                // Don't need to touch $i
            } elseif ($TagName == 'inlineurl') { // We did a big replace early on to turn http:// into [inlineurl]http://
                // Let's say the block can stop at a newline or a space
                $CloseTag = strcspn($Str, " \n\r", $i);
                if ($CloseTag === 0) { // block finishes with URL
                    $CloseTag = $Len;
                }
                if (preg_match('/[!,.?:]+$/', substr($Str, $i, $CloseTag), $Match)) {
                    $CloseTag -= strlen($Match[0]);
                }
                $URL = substr($Str, $i, $CloseTag);
                if (str_ends_with($URL, ')') && substr_count($URL, '(') < substr_count($URL, ')')) {
                    $CloseTag--;
                    $URL = substr($URL, 0, -1);
                }
                $Block = $URL; // Get the URL

                // strcspn returns the number of characters after the offset $i, not after the beginning of the string
                // Therefore, we use += instead of the = everywhere else
                $i += $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } elseif ($WikiLink == true || $TagName == 'n') {
                // Don't need to do anything - empty tag with no closing
            } elseif (in_array($TagName, ['*', '#'])) {
                // We're in a list. Find where it ends
                $NewLine = $i;
                do { // Look for \n[*]
                    $NewLine = strpos($Str, "\n", $NewLine + 1);
                } while ($NewLine !== false && substr($Str, $NewLine + 1, 1 + strlen($TagName)) == "[$TagName");

                $CloseTag = $NewLine;
                if ($CloseTag === false) { // block finishes with list
                    $CloseTag = $Len;
                }
                $Block = substr($Str, $i, $CloseTag - $i); // Get the list
                $i = $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } else {
                //5b) If it's a normal tag, it may have versions of itself nested inside
                $CloseTag = $i - 1;
                $InTagPos = $i - 1;
                $NumInOpens = 0;
                $NumInCloses = -1;

                $InOpenRegex = '/\[(' . $TagName . ')';
                if ($MaxAttribs > 0) {
                    $InOpenRegex .= "(=[^\n'\"\[\]]+)?";
                }
                $InOpenRegex .= '\]/i';

                // Every time we find an internal open tag of the same type, search for the next close tag
                // (as the first close tag won't do - it's been opened again)
                do {
                    $CloseTag = strpos($StrLC, "[/$TagName]", $CloseTag + 1);
                    if ($CloseTag === false) {
                        $CloseTag = $Len;
                        break;
                    } else {
                        $NumInCloses++; // Majority of cases
                    }

                    // Is there another open tag inside this one?
                    $OpenTag = preg_match($InOpenRegex, $Str, $InTag, PREG_OFFSET_CAPTURE, $InTagPos + 1);
                    if (!$OpenTag || $InTag[0][1] > $CloseTag) {
                        break;
                    } else {
                        $InTagPos = $InTag[0][1];
                        $NumInOpens++;
                    }
                } while ($NumInOpens > $NumInCloses);

                // Find the internal block inside the tag
                $Block = substr($Str, $i, $CloseTag - $i); // 5c) Get the contents between [open] and [/close] and call it the block.

                $i = $CloseTag + strlen($TagName) + 3; // 5d) Move the pointer past the end of the [/close] tag.
            }
            // phpcs:enable Generic.CodeAnalysis.EmptyStatement.DetectedIf
            // phpcs:enable Generic.CodeAnalysis.EmptyStatement.DetectedElseif

            // 6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
            switch ($TagName) {
                case 'artist':
                case 'rule':
                case 'tex':
                case 'user':
                    $Array[$ArrayPos] = ['Type' => $TagName, 'Val' => $Block];
                    break;
                case 'aud':
                case 'audio':
                case 'mp3':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = ['Type' => 'aud', 'Val' => $Block];
                    break;
                case 'box':
                    $Array[$ArrayPos] = ['Type' => 'box', 'Val' => self::parse($Block)];
                    break;
                case 'code':
                case 'plain':
                case 'pre':
                    $Block = strtr($Block, ['[inlineurl]' => '']);
                    $Callback = function ($matches) {
                        $n = $matches[2];
                        $text = '';
                        if ($n < 5 && $n > 0) {
                            $e = str_repeat('=', (int)$matches[2] + 1);
                            $text = $e . $matches[3] . $e;
                        }
                        return $text;
                    };
                    $Block = preg_replace_callback('/\[(headline)\=(\d)\](.*?)\[\/\1\]/i', $Callback, $Block);
                    $Block = preg_replace('/\[inlinesize\=3\](.*?)\[\/inlinesize\]/i', '====$1====', $Block);
                    $Block = preg_replace('/\[inlinesize\=5\](.*?)\[\/inlinesize\]/i', '===$1===', $Block);
                    $Block = preg_replace('/\[inlinesize\=7\](.*?)\[\/inlinesize\]/i', '==$1==', $Block);
                    $Array[$ArrayPos] = ['Type' => $TagName, 'Val' => $Block];
                    break;
                case 'collage':
                case 'forum':
                case 'thread':
                    if ((int)$Block or preg_match('/\s*\d+:\d+/', $Block)) {
                        $Array[$ArrayPos] = ['Type' => $TagName, 'Val' => $Block, 'Attr' => $Attrib];
                    } else {
                        $Array[$ArrayPos] = "[{$TagName}]";
                        $i = $TagPos + strlen($Tag[0][0]);
                        ++$ArrayPos;
                    }
                    break;
                case 'image':
                case 'img':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = ['Type' => 'img', 'Val' => $Block];
                    break;
                case 'inlineurl':
                    $Array[$ArrayPos] = ['Type' => 'inlineurl', 'Attr' => $Block, 'Val' => ''];
                    break;
                case 'n':
                    $ArrayPos--;
                    break; // n serves only to disrupt bbcode (backwards compatibility - use [pre])
                case 'pad':
                    $Array[$ArrayPos] = ['Type' => 'pad', 'Attr' => $Attrib, 'Val' => self::parse($Block)];
                    break;
                case 'pl':
                case 'torrent':
                    if ((int)$Block) {
                        $Array[$ArrayPos] = ['Type' => $TagName, 'Val' => $Block, 'Attr' => $Attrib];
                    } else {
                        $Array[$ArrayPos] = "[{$TagName}]";
                        $i = $TagPos + strlen($Tag[0][0]);
                        ++$ArrayPos;
                    }
                    break;
                case 'hide':
                case 'mature':
                case 'spoiler':
                    $Array[$ArrayPos] = ['Type' => $TagName, 'Attr' => $Attrib, 'Val' => self::parse($Block)];
                    break;
                case 'quote':
                    $Array[$ArrayPos] = [
                        'Type' => 'quote',
                        'Attr' => is_object($Attrib) ? $Attrib : self::parse($Attrib),
                        'Val'  => self::parse($Block),
                    ];
                    break;
                case 'url':
                    $Array[$ArrayPos] = ['Type' => 'img', 'Attr' => $Attrib, 'Val' => $Block];
                    if (empty($Attrib)) { // [url]http://...[/url] - always set URL to attribute
                        $Array[$ArrayPos] = ['Type' => 'url', 'Attr' => $Block, 'Val' => ''];
                    } else {
                        $Array[$ArrayPos] = ['Type' => 'url', 'Attr' => $Attrib, 'Val' => self::parse($Block)];
                    }
                    break;
                case '#':
                case '*':
                case '##':
                case '**':
                case '###':
                case '***':
                    $CurrentId = 1;
                    $Array[$ArrayPos] = ['Type' => 'list'];
                    $Array[$ArrayPos]['Val'] = explode("[$TagName]", $Block);
                    $Array[$ArrayPos]['ListType'] = $TagName[0] === '*' ? 'ul' : 'ol';
                    $Array[$ArrayPos]['Tag'] = $TagName;
                    $ChildPrefix = $ListPrefix === '' ? $ListId : $ListPrefix;
                    if ($Attrib !== '' && !is_object($Attrib)) {
                        $ChildPrefix = $Attrib;
                    }
                    foreach ($Array[$ArrayPos]['Val'] as $Key => $Val) {
                        // phpstan complains about:
                        // "Call to function is_string() with string will always evaluate to true."
                        // But if you remove the call (since $Val is always supposed to be a string):
                        // "Parameter #1 $string of function trim expects string, array|string given."
                        // This is more a reflection on the hairiness of this code than anything else
                        if (is_string($Val)) { /** @phpstan-ignore-line */
                            $Id = $ChildPrefix . '.' . $CurrentId++;
                            $Array[$ArrayPos]['Val'][$Key] = self::parse(trim($Val), $Id);
                            $Array[$ArrayPos]['Val'][$Key]['Id'] = $Id;
                        }
                    }
                        $ListId++;
                    break;
                default:
                    if ($WikiLink == true) {
                        $Array[$ArrayPos] = [
                            'Type' => 'wiki',
                            'Val'  => $TagName,
                            'Attr' => $Attrib,
                        ];
                    } else {
                        // Basic tags, like [b] or [size=5]
                        $Array[$ArrayPos] = ['Type' => $TagName, 'Val' => self::parse($Block)];
                        if (!is_object($Attrib) && $MaxAttribs > 0) {
                            $Array[$ArrayPos]['Attr'] = strtolower($Attrib);
                        }
                    }
            }
            $ArrayPos++; // 7) Increment array pointer, start again (past the end of the [/close] tag)
        }
        return $Array;
    }

    /**
     * Generates a navigation list for TOC
     * @param int $Min Minimum number of headlines required for a TOC list
     */
    public static function parse_toc(int $Min = 3, bool $RulesTOC = false): string {
        if (count(self::$Headlines) < $Min) {
            return '';
        }
        $tag = $RulesTOC ? 'ul' : 'ol';
        if ($RulesTOC) {
            $list = "<$tag>";
        } else {
            $list = "<$tag class=\"navigation_list\">";
        }
        $i = 0;
        $level = 0;
        $off = 0;

        foreach (self::$Headlines as $t) {
            $n = (int)$t[0];
            if ($i === 0 && $n > 1) {
                $off = $n - $level;
            }
            self::headline_level($n, $level, $list, $i, $off, $tag);
            $list .= sprintf('<li><a href="#%2$s">%1$s</a>', $t[1], $t[2]);
            $level = $t[0];
            $off = 0;
            $i++;
        }

        return $list . str_repeat("</li></$tag>", $level);
    }

    /**
     * Generates the list items and proper depth
     *
     * First check if the item should be higher than the current level
     * - Close the list and previous lists
     *
     * Then check if the item should go lower than the current level
     * - If the list doesn't open on level one, use the Offset
     * - Open appropriate sub lists
     *
     * Otherwise the item is on the same as level as the previous item
     *
     * $ItemLevel Current item level
     * $Level Current list level
     * $List reference to an XHTML string
     * $i Iterator digit
     * $Offset If the list doesn't start at level 1
     */
    private static function headline_level(int &$ItemLevel, int &$Level, string &$List, int $i, int &$Offset, string $Tag): void {
        if ($ItemLevel < $Level) {
            $diff = $Level - $ItemLevel;
            $List .= '</li>' . str_repeat("</$Tag></li>", $diff);
        } elseif ($ItemLevel > $Level) {
            $diff = $ItemLevel - $Level;
            if ($Offset > 0) {
                $List .= str_repeat("<li><$Tag>", $Offset - 2);
            }

            if ($ItemLevel > 1) {
                $List .= $i === 0 ? '<li>' : '';
                $List .= "\n<$Tag>\n";
            }
        } else {
            $List .= $i > 0 ? '</li>' : '<li>';
        }
    }

    private static function to_html(array $Array, bool $Rules, bool $cache, CacheBucket $bucket): string {
        self::$Levels++;
        /*
         * Hax prevention
         * That's the original comment on this.
         * Most likely this was implemented to avoid anyone nesting enough
         * elements to reach PHP's memory limit as nested elements are
         * solved recursively.
         * Original value of 10, it is now replaced in favor of
         * $MaximumNests.
         * If this line is ever executed then something is, infact
         * being haxed as the if before the block type switch for different
         * tags should always be limiting ahead of this line.
         * (Larger than vs. smaller than.)
         */
        if (self::$Levels > self::$MaximumNests) {
            return $Array['Val']; // Hax prevention, breaks upon exceeding nests.
        }
        $Str = '';

        if (isset($Array['Id'])) {
            if (isset($Array[0]) && is_string($Array[0]) && count($Array) == 2) {
                self::$Levels--;
                return self::smileys(self::userMention($Array[0]));
            }
        }

        foreach ($Array as $Key => $Block) {
            if ($Key === 'Id') {
                continue;
            }
            if (is_string($Block)) {
                $Str .= self::smileys(self::userMention($Block));
                self::$Levels--;
                continue;
            }
            if (self::$Levels < self::$MaximumNests) {
                switch ($Block['Type']) {
                    case 'b':
                        $Str .= '<strong>' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</strong>';
                        break;
                    case 'u':
                        $Str .= '<span style="text-decoration: underline;">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</span>';
                        break;
                    case 'i':
                        $Str .= '<span style="font-style: italic;">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . "</span>";
                        break;
                    case 's':
                        $Str .= '<span style="text-decoration: line-through;">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</span>';
                        break;
                    case 'hr':
                        $Str .= '<hr />';
                        break;
                    case 'important':
                        $Str .= '<strong class="important_text">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</strong>';
                        break;
                    case 'user':
                        $Str .= '<a href="user.php?action=search&amp;search=' . urlencode(trim($Block['Val'], '@')) . '">' . $Block['Val'] . '</a>';
                        break;
                    case 'artist':
                        $Str .= '<a href="artist.php?artistname=' . urlencode(html_unescape($Block['Val'])) . '">' . $Block['Val'] . '</a>';
                        break;
                    case 'rule':
                        $Rule = trim(strtolower($Block['Val']));
                        if (!preg_match('/^[hr]/', $Rule)) {
                            $Rule = "r$Rule";
                        }
                        $Str .= '<a href="rules.php?p=upload#' . urlencode(html_unescape($Rule)) . '">' . preg_replace('/[aA-zZ]/', '', $Block['Val']) . '</a>';
                        break;
                    case 'collage':
                        $Str .= self::bbcodeCollageUrl((int)$Block['Val']);
                        break;
                    case 'forum':
                        $Str .= self::bbcodeForumUrl((int)$Block['Val']);
                        break;
                    case 'thread':
                        $Str .= self::bbcodeThreadUrl((string)$Block['Val']);
                        break;
                    case 'pl':
                        $found = preg_split('/\s*,\s*/m', strtolower($Block['Attr']), -1, PREG_SPLIT_NO_EMPTY);
                        if ($found !== false) {
                            $Str .= \Gazelle\Manager\Torrent::renderPL((int)$Block['Val'], $found);
                        } else {
                            $Str .= "[pl]{$Block['Val']}[/pl]";
                        }
                        break;
                    case 'torrent':
                        $GroupID = 0;
                        if (preg_match(TGROUP_REGEXP, $Block['Val'], $match)) {
                            $GroupID = $match['id'];
                        } elseif ((int)$Block['Val']) {
                            $GroupID = $Block['Val'];
                        }
                        $tgroup = (new Gazelle\Manager\TGroup())->findById((int)$GroupID);
                        if (is_null($tgroup)) {
                            $Str .= '[torrent]' . str_replace('[inlineurl]', '', $Block['Val']) . '[/torrent]';
                        } else {
                            if (str_contains($Block['Attr'], 'noartist')) {
                                $Str .= "<a href=\"{$tgroup->url()}\" title=\"" . ($tgroup->hashTag() ?: 'View torrent group')
                                . '" dir="ltr">' . html_escape($tgroup->name()) . '</a>';
                            } else {
                                $Str .= $tgroup->link();
                            }
                        }
                        break;

                    case 'wiki':
                        $Str .= "<a href=\"wiki.php\">Wiki</a> › "
                            . ($Block['Attr'] instanceof \Gazelle\Wiki
                                ? $Block['Attr']->link()
                                : "[[{$Block['Attr']} ???]]"
                            );
                        break;
                    case 'tex':
                        $Str .= '<katex>' . $Block['Val'] . '</katex>';
                        break;
                    case 'plain':
                        $Str .= $Block['Val'];
                        break;
                    case 'pre':
                        $Str .= '<pre>' . $Block['Val'] . '</pre>';
                        break;
                    case 'code':
                        $Str .= '<code>' . $Block['Val'] . '</code>';
                        break;
                    case 'list':
                        $Str .= "<{$Block['ListType']} class=\"postlist\">";
                        foreach ($Block['Val'] as $Line) {
                            $Str .= '<li' . ($Rules ? ' id="r' . $Line['Id'] . '"' : '') . '>' . self::to_html($Line, $Rules, $cache, $bucket) . '</li>';
                        }
                        $Str .= '</' . $Block['ListType'] . '>';
                        break;
                    case 'align':
                        $ValidAttribs = ['left', 'center', 'right'];
                        if (!in_array($Block['Attr'], $ValidAttribs)) {
                            $Str .= '[align=' . $Block['Attr'] . ']' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '[/align]';
                        } else {
                            $Str .= '<div style="text-align: ' . $Block['Attr'] . ';">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</div>';
                        }
                        break;
                    case 'color':
                    case 'colour':
                        $Block['Attr'] = strtolower($Block['Attr']);
                        if (!in_array($Block['Attr'], self::$ColorName) && !preg_match('/^#[0-9a-f]{6}$/', $Block['Attr'])) {
                            $Str .= '[color=' . $Block['Attr'] . ']' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '[/color]';
                        } else {
                            $Str .= '<span style="color: ' . $Block['Attr'] . ';">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</span>';
                        }
                        break;
                    case 'headline':
                        $text = self::to_html($Block['Val'], $Rules, $cache, $bucket);
                        $raw = self::raw_text($Block['Val']);
                        if (!in_array($Block['Attr'], self::$HeadlineLevels)) {
                            $Str .= sprintf('%1$s%2$s%1$s', str_repeat('=', (int)$Block['Attr'] + 1), $text);
                        } else {
                            $id = '_' . crc32($raw . self::$HeadlineID);
                            if (self::$InQuotes === 0) {
                                self::$Headlines[] = [$Block['Attr'], $raw, $id];
                            }

                            $Str .= sprintf('<h%1$d id="%3$s">%2$s</h%1$d>', ($Block['Attr'] + 2), $text, $id);
                            self::$HeadlineID++;
                        }
                        break;
                    case 'inlinesize':
                    case 'size':
                        $ValidAttribs = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
                        if (!in_array($Block['Attr'], $ValidAttribs)) {
                            $Str .= '[size=' . $Block['Attr'] . ']' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '[/size]';
                        } else {
                            $Str .= '<span class="size' . $Block['Attr'] . '">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</span>';
                        }
                        break;
                    case 'quote':
                        self::$NoImg++; // No images inside quote tags
                        self::$InQuotes++;
                        if (self::$InQuotes == self::$NestsBeforeHide) { //Put quotes that are nested beyond the specified limit in [hide] tags.
                            $Str .= '<strong>Older quotes</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                            $Str .= '<blockquote class="hidden spoiler">';
                        }
                        if (!empty($Block['Attr'])) {
                            $Exploded = explode('|', self::to_html($Block['Attr'], $Rules, $cache, $bucket));
                            if (isset($Exploded[1]) && (is_numeric($Exploded[1]) || (in_array($Exploded[1][0], ['a', 't', 'c', 'r']) && is_numeric(substr($Exploded[1], 1))))) {
                                // the part after | is either a number or starts with a, t, c or r, followed by a number (forum post, artist comment, torrent comment, collage comment or request comment, respectively)
                                $PostID = trim($Exploded[1]);
                                $Str .= '<a href="#" onclick="QuoteJump(event, \'' . $PostID . '\'); return false;"><strong class="quoteheader">' . $Exploded[0] . '</strong> wrote: </a>';
                            } else {
                                $Str .= '<strong class="quoteheader">' . $Exploded[0] . '</strong> wrote: ';
                            }
                        }
                        $Str .= '<blockquote>' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</blockquote>';
                        if (self::$InQuotes == self::$NestsBeforeHide) { //Close quote the deeply nested quote [hide].
                            $Str .= '</blockquote><br />'; // Ensure new line after quote train hiding
                        }
                        self::$NoImg--;
                        self::$InQuotes--;
                        break;
                    case 'box':
                        $Str .= '<div class="box pad" style="padding: 10px 10px 10px 20px">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</div>';
                        break;
                    case 'pad':
                        $Attr = array_filter(explode('|', $Block['Attr'] ?? ''), fn($x) => is_numeric($x) && (float)$x >= 0);
                        if (count($Attr) === 0) {
                            $Str .= self::to_html($Block['Val'], $Rules, $cache, $bucket);
                        } else {
                            $Padding = implode(' ', array_map(fn($x) => "{$x}px", $Attr));
                            $Str .= "<span style=\"display: inline-block; padding: {$Padding}\">" . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</span>';
                        }
                        break;
                    case 'hide':
                    case 'spoiler':
                        $Str .= '<strong>' . ($Block['Attr'] ?: 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                        $Str .= '<blockquote class="hidden spoiler">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</blockquote>';
                        break;
                    case 'mature':
                        if (self::$viewer->option('EnableMatureContent')) {
                            if (!empty($Block['Attr'])) {
                                $Str .= '<strong class="mature" style="font-size: 1.2em;">Mature content:</strong><strong> ' . $Block['Attr'] . '</strong><br /> <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                                $Str .= '<blockquote class="hidden spoiler">' . self::to_html($Block['Val'], $Rules, $cache, $bucket) . '</blockquote>';
                            } else {
                                $Str .= '<strong>Use of the [mature] tag requires a description.</strong> The correct format is as follows: <strong>[mature=description] ...content... [/mature]</strong>, where "description" is a mandatory description of the post. Misleading descriptions will be penalized. For further information on our mature content policies, please refer to this <a href="wiki.php?action=article&amp;id=1063">wiki</a>.';
                            }
                        } else {
                            $Str .= '<span class="mature_blocked" style="font-style: italic;"><a href="wiki.php?action=article&amp;id=1063">Mature content</a> has been blocked. You can choose to view mature content by editing your <a href="user.php?action=edit&amp;id=me">settings</a>.</span>';
                        }
                        break;
                    case 'img':
                        if (self::$NoImg > 0 && self::valid_url($Block['Val'])) {
                            $Str .= '<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (image)';
                            break;
                        }
                        if (!self::valid_url($Block['Val'], '\.(?:avif|bmp|gif|jpe?g|png|svg|tiff|webp)')) {
                            $Str .= "[img]{$Block['Val']}[/img]";
                        } else {
                            $LocalURL = self::local_url($Block['Val']);
                            if ($LocalURL) {
                                $Str .= '<img loading="lazy" class="scale_image" onclick="lightbox.init(this, $(this).width());" alt="' . $Block['Val'] . '" src="' . $LocalURL . '" />';
                            } else {
                                if ($cache) {
                                    $image    = image_cache_encode($Block['Val'], bucket: $bucket);
                                    $original = " data-origin-src=\"{$Block['Val']}\"";
                                } else {
                                    $image    = $Block['Val'];
                                    $original = "";
                                }
                                $Str .= "<img loading=\"lazy\" class=\"scale_image\" onclick=\"lightbox.init(this, $(this).width());\" alt=\"$image\" src=\"$image\"$original />";
                            }
                        }
                        break;

                    case 'aud':
                        if (self::$NoImg > 0 && self::valid_url($Block['Val'])) {
                            $Str .= '<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (audio)';
                            break;
                        }
                        if (!self::valid_url($Block['Val'], '\.(?:mp3|ogg|wav)')) {
                            $Str .= '[aud]' . $Block['Val'] . '[/aud]';
                        } else {
                            //TODO: Proxy this for staff?
                            $Str .= '<audio controls="controls" src="' . $Block['Val'] . '"><a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a></audio>';
                        }
                        break;

                    case 'url':
                        // Make sure the URL has a label
                        if (empty($Block['Val'])) {
                            $Block['Val'] = $Block['Attr'];
                            $NoName = true; // If there isn't a Val for this
                        } else {
                            $Block['Val'] = self::to_html($Block['Val'], $Rules, $cache, $bucket);
                            $NoName = false;
                        }

                        if (!self::valid_url($Block['Attr'])) {
                            if (self::relative_url($Block['Attr'])) {
                                $Str .= '<a href="' . $Block['Attr'] . '">' . $Block['Val'] . '</a>';
                            } else {
                                $Str .= '[url=' . $Block['Attr'] . ']' . $Block['Val'] . '[/url]';
                            }
                        } else {
                            $LocalURL = self::local_url($Block['Attr']);
                            if ($LocalURL) {
                                if ($NoName) {
                                    $Block['Val'] = substr($LocalURL, 1);
                                }
                                if ($resolved = self::resolve_url($Block['Val'])) {
                                    $Str .= $resolved;
                                } else {
                                    $Str .= '<a href="' . $LocalURL . '">' . $Block['Val'] . '</a>';
                                }
                            } else {
                                if ($resolved = self::resolve_url($Block['Val'])) {
                                    $Str .= $resolved;
                                } else {
                                    $Str .= '<a rel="noreferrer" target="_blank" href="' . $Block['Attr'] . '">' . $Block['Val'] . '</a>';
                                }
                            }
                        }
                        break;

                    case 'inlineurl':
                        if (!self::valid_url($Block['Attr'], '', true)) {
                            $Array = self::parse($Block['Attr']);
                            $Block['Attr'] = $Array;
                            $Str .= self::to_html($Block['Attr'], $Rules, $cache, $bucket);
                        } else {
                            $LocalURL = self::local_url($Block['Attr']);
                            if ($LocalURL) {
                                $Str .= self::resolve_url($Block['Attr'])
                                ?? ('<a href="' . $LocalURL . '">' . substr($LocalURL, 1) . '</a>');
                            } else {
                                $Str .= self::resolve_url($Block['Attr'])
                                ?? sprintf('<a rel="noreferrer" target="_blank" href="%s">%s</a>', $Block['Attr'], $Block['Attr']);
                            }
                        }

                        break;
                }
            }
        }
        self::$Levels--;
        return $Str;
    }

    private static function raw_text(array $Array): string {
        $Str = '';
        foreach ($Array as $Block) {
            if (is_string($Block)) {
                $Str .= $Block;
                continue;
            }
            switch ($Block['Type']) {
                case 'headline':
                case 'tex': //since this will never strip cleanly, just remove it
                    break;
                case 'b':
                case 'u':
                case 'i':
                case 's':
                case 'color':
                case 'size':
                case 'quote':
                case 'align':
                case 'pad':
                    $Str .= self::raw_text($Block['Val']);
                    break;
                case 'artist':
                case 'user':
                case 'wiki':
                case 'pre':
                case 'code':
                case 'aud':
                case 'img':
                    $Str .= $Block['Val'];
                    break;
                case 'list':
                    foreach ($Block['Val'] as $Line) {
                        $Str .= $Block['Tag'] . self::raw_text($Line);
                    }
                    break;

                case 'url':
                    // Make sure the URL has a label
                    if (empty($Block['Val'])) {
                        $Block['Val'] = $Block['Attr'];
                    } else {
                        $Block['Val'] = self::raw_text($Block['Val']);
                    }

                    $Str .= $Block['Val'];
                    break;

                case 'inlineurl':
                    if (!self::valid_url($Block['Attr'], '', true)) {
                        $Array = self::parse($Block['Attr']);
                        $Block['Attr'] = $Array;
                        $Str .= self::raw_text($Block['Attr']);
                    } else {
                        $Str .= $Block['Attr'];
                    }

                    break;
            }
        }
        return $Str;
    }

    private static function userMention(string $text): string {
        return preg_replace_callback('/(?<=^|\W)@' . str_replace('/', '', USERNAME_REGEXP) . '/',
            function ($match) {
                $username = $match['username'];
                static $cache;
                if (!isset($cache[$username])) {
                    $userMan = new \Gazelle\Manager\User();
                    $user = $userMan->findByUsername($username);
                    if (is_null($user) && preg_match('/^(.*)[.?]+$/', $username, $match)) {
                        // strip off trailing dots to see if we can match @Spine...
                        $username = $match[1];
                        $user = $userMan->findByUsername($username);
                    }
                    if ($user) {
                        $cache[$username] = $user;
                    }
                }
                return !isset($cache[$username])
                    ? "@$username"
                    : sprintf('<a href="%s">@%s</a>', $cache[$username]->url(), $cache[$username]->username());
            },
            $text
        );
    }

    private static function smileys(string $Str): string {
        if (!isset(self::$viewer) || self::$viewer->option('DisableSmileys')) {
            return $Str;
        }
        if (count(self::$ProcessedSmileys) == 0 && count(self::$Smileys) > 0) {
            foreach (self::$Smileys as $Key => $Val) {
                self::$ProcessedSmileys[$Key] = '<img border="0" src="' . STATIC_SERVER . '/common/smileys/' . $Val . '" alt="" />';
            }
            reset(self::$ProcessedSmileys);
        }
        return strtr($Str, self::$ProcessedSmileys);
    }

    protected static function bbcodeCollageUrl(int $id): string {
        $collage = (new \Gazelle\Manager\Collage())->findById($id);
        return $collage?->link() ?? "[collage]{$id}[/collage]";
    }

    protected static function bbcodeForumUrl(int $id): string {
        $forum = (new \Gazelle\Manager\Forum())->findById($id);
        if (is_null($forum)) {
            return "[forum]{$id}[/forum]";
        }
        if (!self::$viewer->readAccess($forum)) {
            return "[restricted forum]";
        }
        return $forum->link();
    }

    protected static function bbcodeCommentUrl(int $postId): ?string {
        $post = (new Gazelle\Manager\Comment())->findById($postId);
        if (is_null($post)) {
            return null;
        }
        // FIXME: this should give context about where the comment was posted (artist/collage/request/tgroup name)
        return sprintf('<a href="%s">%s Comment #%s</a>',
                       $post->url(), ucfirst($post->page()), $postId);
    }

    protected static function bbcodePostUrl(int $postId): ?string {
        $post = (new \Gazelle\Manager\ForumPost())->findById($postId);
        if (is_null($post)) {
            return null;
        }
        return self::bbcodeThreadUrl($post->threadId(), $post->id());
    }

    protected static function bbcodeThreadUrl(int|string $thread, int|null $postId = null): string {
        if (is_string($thread) && str_contains($thread, ':')) {
            [$threadId, $postId] = array_map('intval', explode(':', $thread));
        } else {
            $threadId = (int)$thread;
        }
        if (!$threadId) {
            return "[thread]{$thread}[/thread]";
        }

        $thread = (new \Gazelle\Manager\ForumThread())->findById($threadId);
        if (is_null($thread)) {
            return $postId ? "[thread]{$threadId}:{$postId}[/thread]" : "[thread]{$threadId}[/thread]";
        }
        if (!self::$viewer->readAccess($thread->forum())) {
            return sprintf('<a href="forums.php?action=viewforum&amp;forumid=%d">%s</a>', $thread->forum()->id(), 'restricted');
        }

        if ($postId) {
            return sprintf('<a href="forums.php?action=viewthread&amp;threadid=%d&amp;postid=%s#post%s">%s%s (Post #%s)</a>',
                $threadId, $postId, $postId, ($thread->isLocked() ? "🔒 " : ''), $thread->title(), $postId);
        }
        return sprintf('<a href="forums.php?action=viewthread&amp;threadid=%d">%s%s</a>',
            $threadId, ($thread->isLocked() ? "🔒 " : ''), $thread->title());
    }
}
