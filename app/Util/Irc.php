<?php

namespace Gazelle\Util;

class Irc
{
    public static function sendRaw(string $raw) {
        if (defined('DISABLE_IRC') && DISABLE_IRC === true) {
            return;
        }
        $ircSocket = fsockopen(SOCKET_LISTEN_ADDRESS, SOCKET_LISTEN_PORT);
        $raw = str_replace(["\n", "\r"], '', $raw);
        fwrite($ircSocket, $raw);
        fclose($ircSocket);
    }

    public static function sendChannel(string $message, string $channel = null) {
        if ($channel == null) {
            $channel = MOD_CHAN;
        }

        self::sendRaw("PRIVMSG $channel :$message");
    }
}
