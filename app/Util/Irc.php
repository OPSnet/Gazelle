<?php

namespace Gazelle\Util;

class Irc {
    public static function render(mixed ...$list): string {
        return implode('', array_map(
            fn($s) => is_a($s, \Gazelle\Util\IrcText::class) ? $s->value : $s,
            $list)
        );
    }

    public static function sendMessage(string $target, string $message): bool {
        if (DISABLE_IRC) {
            return true;
        }
        $curl = new Curl();
        $curl->setUseProxy(false)
            ->setMethod(CurlMethod::POST)
            ->setOption(CURLOPT_POSTFIELDS, $message)
            ->setOption(CURLOPT_HTTPHEADER, ['Content-Type: plain/text']);
        return $curl->fetch(IRC_HTTP_SOCKET_ADDRESS . 'irc_msg/' . urlencode($target));
    }
}
