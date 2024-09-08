<?php

namespace Gazelle\Util;

use Gazelle\Enum\CacheBucket;

class Twig {
    protected static \Gazelle\Manager\User $userMan;

    public static function setUserMan(\Gazelle\Manager\User $userMan): void {
        self::$userMan = $userMan;
    }

    public static function factory(): \Twig\Environment {
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../' . TEMPLATE_PATH), [
                'debug' => DEBUG_MODE,
                'cache' => __DIR__ . '/../../cache/twig'
            ]);

        $twig->addFilter(new \Twig\TwigFilter(
            'article',
            fn($word) => preg_match('/^[aeiou]/i', $word) ? 'an' : 'a'
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'avatar',
            function (\Gazelle\User $user, \Gazelle\User $viewer): string {
                $data      = $viewer->avatarComponentList($user);
                $basicAttr = ['class="avatar_0" width="' . AVATAR_WIDTH . '"'];
                $hoverAttr = ['class="avatar_1" width="' . AVATAR_WIDTH . '"'];
                $text      = $data['text'];
                if ($text !== false && $text != '') {
                    $basicAttr[] = 'title="' . html_escape($text) . '" alt="' . html_escape($text) . '"';
                    $hoverAttr[] = 'title="' . html_escape($text) . '" alt="' . html_escape($text) . '"';
                }

                $image = $data['image'];
                if ($image === USER_DEFAULT_AVATAR) {
                    $basicAttr[] = "src=\"$image\"";
                } else {
                    $basicAttr[] = 'src="' . html_escape(image_cache_encode($image, width: AVATAR_WIDTH))
                        . '" loading="lazy" data-origin-src="' . html_escape($image) . '"';
                }

                $rollover = $data['hover'];
                if ($rollover) {
                    $hoverAttr[] = 'src="' . html_escape(image_cache_encode($rollover, width: AVATAR_WIDTH))
                        . '" loading="eager" data-origin-src="' . html_escape($rollover) . '"';
                }
                return '<div class="avatar_container"><div><img ' . implode(' ', $basicAttr) . " /></div>"
                    . ($rollover ? ('<div><img ' . implode(' ', $hoverAttr) . ' /></div>') : '')
                    . "</div>";
            },
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'b64',
            fn(string $binary) => base64_encode($binary)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'badge_list',
            fn(\Gazelle\User $user) => $user->privilege()->badgeList()
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'bb_format',
            fn($text, $outputToc = true) => new \Twig\Markup(\Text::full_format($text, $outputToc, cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::forum), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'bb_forum',
            fn($text) => new \Twig\Markup(\Text::full_format($text, OutputTOC: false, cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::forum), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'column',
            fn(\Gazelle\Util\SortableTableHeader $header, string $name) => new \Twig\Markup($header->emit($name), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'image', fn(?string $image) => $image ? image_cache_encode($image) : $image
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'image_cache',
            fn(?string $image, mixed $height = 0, mixed $width = 0)
                => $image ? image_cache_encode(url: $image, height: (int)$height, width: (int)$width) : $image
        ));
        $twig->addFilter(new \Twig\TwigFilter(
            'image_proxy',
            fn(?string $image, mixed $proxy = true)
                => ((bool)$proxy && $image) ? image_cache_encode(url: $image, proxy: true) : $image
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'linkify',
            function ($link) {
                $local = \Text::local_url($link);
                if ($local !== false) {
                    $link = \Text::resolve_url($link);
                }
                return new \Twig\Markup("<a href=\"$link\">$link</a>", 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'octet_size',
            fn($size, array $option = []) => byte_format($size, empty($option) ? 2 : $option[0]),
            ['is_variadic' => true]
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'plural',
            fn($number, $plural = 's') => plural($number, $plural)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'repeat',
            fn($text, $number) => str_repeat($text, $number)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'shorten',
            fn(string $text, int $length) => shortenString($text, $length)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_compact',
            fn(int $seconds) => new \Twig\Markup(Time::convertSeconds($seconds), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_diff',
            fn($time, $levels = 2, $span = true) => new \Twig\Markup(time_diff($time, $levels, $span), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_interval',
            fn($time, $levels = 2) => new \Twig\Markup(
                time_diff((string)\Gazelle\Util\Time::timeAgo($time), $levels, span: false, hideAgo: true),
                'UTF-8'
            )
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'token_count',
            fn($size) => (int)ceil((int)$size / BYTES_PER_FREELEECH_TOKEN)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'truth',
            fn(bool $truth) => $truth ? "\xe2\x9c\x85" : "\xe2\x9d\x8c"
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ucfirst',
            fn($text) => ucfirst($text)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ucfirstall',
            fn($text) => ucfirst(
                implode(' ', array_map(fn($w) => ucfirst($w), explode(' ', $text)))
            )
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'user_url',
            fn($userId) => new \Twig\Markup(\Users::format_username($userId, false, false, false), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'user_full',
            fn($userId) => new \Twig\Markup(\Users::format_username($userId, true, true, true, true), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'user_status',
            function ($userId, $viewer): \Twig\Markup {
                $user = self::$userMan->findById($userId);
                if (is_null($user)) {
                    return new \Twig\Markup('', 'UTF-8');
                }
                $icon = [(new \Gazelle\User\Donor($user))->heart($viewer)];
                if ($user->isWarned()) {
                    $icon[] = '<a href="wiki.php?action=article&amp;name=warnings"><img src="'
                        . STATIC_SERVER . '/common/symbols/warned.png" alt="Warned" title="Warned'
                        . ($viewer->id() == $user->id() ? ' - Expires ' . date('Y-m-d H:i', $user->warningExpiry()) : '')
                        . '" class="tooltip" /></a>';
                }
                if ($user->isDisabled()) {
                    $icon[] = '<a href="rules.php"><img src="'
                        . STATIC_SERVER . '/common/symbols/disabled.png" alt="Banned" title="Disabled" class="tooltip" /></a>';
                }
                return new \Twig\Markup(implode(' ', $icon), 'UTF-8');
            }
        ));

        $twig->addFunction(new \Twig\TwigFunction('header', fn($title, $options = []) => new \Twig\Markup(
            \View::header($title, $options),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('footer', fn(bool $showDisclaimer = false) => new \Twig\Markup(
            \View::footer($showDisclaimer),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('donor_icon', fn($icon, $userId) => new \Twig\Markup(image_cache_encode($icon), 'UTF-8')));

        $twig->addFunction(new \Twig\TwigFunction('ipaddr', fn(string $ipaddr) => new \Twig\Markup(
            "$ipaddr <a href=\"user.php?action=search&amp;ip_history=on&amp;matchtype=strict&amp;ip=$ipaddr\" title=\"Search\" class=\"brackets tooltip\">S</a>",
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('mtime', fn($filename) => new \Twig\Markup(
            base_convert(filemtime(SERVER_ROOT . '/public/static/' . $filename), 10, 36),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('mtime_scss', fn($filename) => new \Twig\Markup(
            base_convert(filemtime(SERVER_ROOT . '/sass/' . preg_replace('/\.css$/', '.scss', $filename)), 10, 36),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('mtime_css', fn($filename) => new \Twig\Markup(
            base_convert(filemtime(SERVER_ROOT . '/public/static/styles/' . $filename), 10, 36),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('privilege', function ($default, $config, $key) {
            $enabled = $config[$key] ?? $default[$key] ?? false;
            $isOverride = isset($config[$key], $default) && $config[$key] !== ($default[$key] ?? null);
            return new \Twig\Markup(
                sprintf(
                    '<input type="checkbox" name="%s" id="%s" value="1"%s />&nbsp;<label title="%s" for="%s"%s>%s</label><br />',
                    "perm_$key", $key, $enabled ? ' checked="checked"' : '', $key, $key,
                    $isOverride ? ' style="font-weight: bold;"' : '',
                    \Gazelle\Manager\Privilege::privilegeList()[$key] ?? "!unknown($key)!"
                ),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('ratio',
            fn($up, $down) => new \Twig\Markup(ratio_html($up, $down), 'UTF-8'))
        );

        $twig->addFunction(new \Twig\TwigFunction('resolveCountryIpv4', fn($addr) => new \Twig\Markup(
            (function ($ip) {
                static $cache = [];
                if (!isset($cache[$ip])) {
                    $Class = strtr($ip, '.', '-');
                    $cache[$ip] = '<span class="cc_' . $Class . '">Resolving CC...'
                        . '<script type="text/javascript">'
                            . "document.addEventListener('DOMContentLoaded', function() {"
                                . '$.get(\'tools.php?action=get_cc&ip=' . $ip . '\', function(cc) {'
                                    . '$(\'.cc_' . $Class . '\').html(cc);'
                                . '});'
                            . '});'
                        . '</script></span>';
                }
                return $cache[$ip];
            })($addr),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('shorten', fn($text, $length) => new \Twig\Markup(
            shortenString($text, $length),
            'UTF-8'
        )));

        // round up number to next closest power of 10 of n/10
        // 120 => 120, but 121 => 130, 129 => 130
        // All because Twig does not expose log10 as a function
        $twig->addFunction(new \Twig\TwigFunction('upscale', fn($number) => new \Twig\Markup(
            (function ($number) {
                $scale = (10 ** floor(log10($number / 10)));
                return ceil($number / $scale) * $scale;
            })($number),
            'UTF-8'
        )));

        $twig->addTest(new \Twig\TwigTest('donor', fn($user) => !is_null($user) && $user::class === \Gazelle\User::class && (new \Gazelle\User\Donor($user))->isDonor()));
        $twig->addTest(new \Twig\TwigTest('forum_thread', fn($thread) => $thread instanceof \Gazelle\ForumThread));

        $twig->addTest(new \Twig\TwigTest('nan', fn($value) => is_nan($value)));

        $twig->addTest(new \Twig\TwigTest('request_fill', fn($contest) => $contest instanceof \Gazelle\Contest\RequestFill));

        $twig->addGlobal('dom', new \Gazelle\Util\Dominator());

        return $twig;
    }
}
