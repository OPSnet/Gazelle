<?php

namespace Gazelle\Util;

class Irc
{
    public static function sendRaw(string $raw) {
        if (DISABLE_IRC) {
            return;
        }
        $ircSocket = @fsockopen(IRC_SOCKET_LISTEN_ADDRESS, IRC_SOCKET_LISTEN_PORT);
        if ($ircSocket) {
            fwrite($ircSocket, str_replace(["\n", "\r"], '', $raw));
            fclose($ircSocket);
        }
    }

    public static function sendChannel(string $message, string $channel = null) {
        if ($channel == null) {
            $channel = MOD_CHAN;
        }
        self::sendRaw("PRIVMSG $channel :$message");
    }
}
