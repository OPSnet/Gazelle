<?php

namespace Gazelle\Util;

class Twig {
    protected static \Gazelle\Manager\User $userMan;

    public static function setUserMan(\Gazelle\Manager\User $userMan) {
        self::$userMan = $userMan;
    }

    public static function factory(): \Twig\Environment {
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates'), [
                'debug' => DEBUG_MODE,
                'cache' => __DIR__ . '/../../cache/twig'
        ]);

        $twig->addFilter(new \Twig\TwigFilter(
            'article',
            fn($word) => preg_match('/^[aeiou]/i', $word) ? 'an' : 'a'
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'b64',
            fn(string $binary) => base64_encode($binary)
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'badge_list',
            fn(\Gazelle\User $user) => (new \Gazelle\User\Privilege($user))->badgeList()
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'bb_format',
            fn($text, $outputToc = true) => new \Twig\Markup(\Text::full_format($text, $outputToc), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'checked',
            fn($isChecked) => new \Twig\Markup($isChecked ? ' checked="checked"' : '', 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'column',
            fn(\Gazelle\Util\SortableTableHeader $header, string $name) => new \Twig\Markup($header->emit($name), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'image',
            function ($i) {
                global $Viewer; // this is sad
                return new \Twig\Markup((new ImageProxy($Viewer))->process($i), 'UTF-8');
            }
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
            fn(string $text, int $length) => new \Twig\Markup(shortenString($text, $length), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_diff',
            fn($time, $levels = 2) => new \Twig\Markup(time_diff($time, $levels), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_interval',
            fn(int $seconds) => new \Twig\Markup(Time::convertSeconds($seconds), 'UTF-8')
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
            fn($text) => new \Twig\Markup(ucfirst($text), 'UTF-8')
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ucfirstall',
            fn($text) => new \Twig\Markup(ucfirst(
                implode(' ', array_map(fn($w) => ucfirst($w), explode(' ', $text)))
            ), 'UTF-8')
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
            function ($userId, $viewer): string {
                $user = self::$userMan->findById($userId);
                if (is_null($user)) {
                    return '';
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

        $twig->addFunction(new \Twig\TwigFunction('header', fn($title, $options = '') => new \Twig\Markup(
            \View::show_header($title, $options),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('footer', fn($options = []) => new \Twig\Markup(
            \View::show_footer($options),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('donor_icon', function($icon, $userId) {
            global $Viewer;
            return new \Twig\Markup(
                (new ImageProxy($Viewer))->process($icon, 'donoricon', $userId),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('ipaddr', fn(string $ipaddr) => new \Twig\Markup(
            "$ipaddr <a href=\"user.php?action=search&amp;ip_history=on&amp;matchtype=strict&amp;ip="
                . $ipaddr . '" title="Search" class="brackets tooltip">S</a>'
            , 'UTF-8'
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

        $twig->addFunction(new \Twig\TwigFunction('privilege', fn($default, $config, $key) => new \Twig\Markup(
            ($default
                ? sprintf(
                    '<input id="%s" type="checkbox" disabled="disabled"%s />&nbsp;',
                    "default_$key", (isset($default[$key]) && $default[$key] ? ' checked="checked"' : '')
                )
                : ''
            )
            . sprintf(
                '<input type="checkbox" name="%s" id="%s" value="1"%s />&nbsp;<label title="%s" for="%s">%s</label><br />',
                "perm_$key", $key, (empty($config[$key]) ? '' : ' checked="checked"'), $key, $key,
                \Gazelle\Manager\Privilege::privilegeList()[$key] ?? "!unknown($key)!"
            ),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('ratio',
            fn($up, $down) => new \Twig\Markup(ratio_html($up, $down), 'UTF-8'))
        );

        $twig->addFunction(new \Twig\TwigFunction('resolveCountryIpv4', fn($addr) => new \Twig\Markup(
            (function ($ip) {
                static $cache = [];
                if (!isset($cache[$ip])) {
                    $Class = strtr($ip, '.', '-');
                    $cache[$ip] = '<span class="cc_'.$Class.'">Resolving CC...'
                        . '<script type="text/javascript">'
                            . '$(document).ready(function() {'
                                . '$.get(\'tools.php?action=get_cc&ip='.$ip.'\', function(cc) {'
                                    . '$(\'.cc_'.$Class.'\').html(cc);'
                                . '});'
                            . '});'
                        . '</script></span>';
                }
                return $cache[$ip];
            })($addr),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('resolveIpv4', fn($addr) => new \Twig\Markup(
            (function ($ip) {
                if (!$ip) {
                    $ip = '127.0.0.1';
                }
                static $cache = [];
                if (!isset($cache[$ip])) {
                    $class = strtr($ip, '.', '-');
                    $cache[$ip] = '<span class="host_' . $class
                        . '">Resolving host' . "\xE2\x80\xA6" . '<script type="text/javascript">$(document).ready(function() {'
                        .  "\$.get('tools.php?action=get_host&ip=$ip', function(host) {\$('.host_$class').html(host)})})</script></span>";
                }
                return $cache[$ip];
            })($addr),
            'UTF-8'
        )));

        $twig->addFunction(new \Twig\TwigFunction('shorten', fn($text, $length) => new \Twig\Markup(
            shortenString($text, $length),
            'UTF-8'
        )));

        $twig->addTest(new \Twig\TwigTest('donor', fn($user) => !is_null($user) && $user::class === \Gazelle\User::class && (new \Gazelle\User\Donor($user))->isDonor()));

        $twig->addTest(new \Twig\TwigTest('nan', fn($value) => is_nan($value)));

        $twig->addTest(new \Twig\TwigTest('request_fill', fn($contest) => $contest instanceof \Gazelle\Contest\RequestFill));

        $twig->addGlobal('dom', new \Gazelle\Util\Dominator);

        return $twig;
    }
}
