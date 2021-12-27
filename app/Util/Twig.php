<?php

namespace Gazelle\Util;

class Twig {
    public static function factory(): \Twig\Environment {
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates'), [
                'debug' => DEBUG_MODE,
                'cache' => __DIR__ . '/../../cache/twig'
        ]);

        $twig->addFilter(new \Twig\TwigFilter(
            'article',
            function ($word) {
                return preg_match('/^[aeiou]/i', $word) ? 'an' : 'a';
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'b64',
            function (string $binary) {
                return base64_encode($binary);
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'bb_format',
            function ($text, $outputToc = true) {
                return new \Twig\Markup(\Text::full_format($text, $outputToc), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'checked',
            function ($isChecked) {
                return $isChecked ? ' checked="checked"' : '';
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'column',
            function (\Gazelle\Util\SortableTableHeader $header, string $name) {
                return new \Twig\Markup($header->emit($name), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'image',
            function ($i) {
                return new \Twig\Markup((new ImageProxy)->process($i), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ipaddr',
            function ($ipaddr) {
                return new \Twig\Markup(\Tools::display_ip($ipaddr), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'octet_size',
            function ($size, array $option = []) {
                return \Format::get_size($size, empty($option) ? 2 : $option[0]);
            },
            ['is_variadic' => true]
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'plural',
            function ($number, $plural = '') {
                return plural($number, $plural);
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'selected',
            function ($isSelected) {
                return $isSelected ? ' selected="selected"' : '';
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'shorten',
            function (string $text, int $length) {
                return shortenString($text, $length);
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_diff',
            function ($time) {
                return new \Twig\Markup(time_diff($time), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'time_interval',
            function (int $seconds) {
                return new \Twig\Markup(Time::convertSeconds($seconds), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'token_count',
            function ($size) {
                return (int)ceil((int)$size / BYTES_PER_FREELEECH_TOKEN);
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ucfirst',
            function ($text) {
                return ucfirst($text);
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'ucfirstall',
            function ($text) {
                return implode(' ', array_map(fn($w) => ucfirst($w), explode(' ', $text)));
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'user_url',
            function ($userId) {
                return new \Twig\Markup(\Users::format_username($userId, false, false, false), 'UTF-8');
            }
        ));

        $twig->addFilter(new \Twig\TwigFilter(
            'user_full',
            function ($userId) {
                return new \Twig\Markup(\Users::format_username($userId, true, true, true, true), 'UTF-8');
            }
        ));

        $twig->addFunction(new \Twig\TwigFunction('header', function ($title, $options = '') {
            return new \Twig\Markup(
                \View::show_header($title, $options),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('footer', function ($options = []) {
            return new \Twig\Markup(
                \View::show_footer($options),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('donor_icon', function($icon, $userId) {
            return new \Twig\Markup(
                (new ImageProxy)->process($icon, 'donoricon', $userId),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('mtime', function($filename) {
            return new \Twig\Markup(
                base_convert(filemtime(SERVER_ROOT . '/public/static/' . $filename), 10, 36),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('mtime_css', function($filename) {
            return new \Twig\Markup(
                base_convert(filemtime(SERVER_ROOT . '/sass/' . preg_replace('/\.css$/', '.scss', $filename)), 10, 36),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('privilege', function ($default, $config, $key) {
            return new \Twig\Markup(
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
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('ratio', function ($up, $down) {
            return new \Twig\Markup(
                \Format::get_ratio_html($up, $down),
                'UTF-8'
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('resolveCountryIpv4', function ($addr) {
            return new \Twig\Markup(
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
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('resolveIpv4', function ($addr) {
            return new \Twig\Markup(
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
            );
        }));

        $twig->addFunction(new \Twig\TwigFunction('shorten', function ($text, $length) {
            return new \Twig\Markup(
                shortenString($text, $length),
                'UTF-8'
            );
        }));

        $twig->addTest(
            new \Twig\TwigTest('numeric', function ($value) {
                return is_numeric($value);
            })
        );

        return $twig;
    }
}

