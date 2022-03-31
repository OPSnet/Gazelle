<?php

namespace Gazelle\Util;

class Irc
{
    public static function sendRaw(string $raw) {
        // TODO: remove this method once all callers use sendChannel()
        $rawArray = explode(' ', $raw, 3);
        if (count($rawArray) !== 3 || $rawArray[0] !== 'PRIVMSG') {
            // raise exception here maybe; just don't call sendRaw() at all
            return false;
        }
        return self::sendChannel(substr($rawArray[2], 1), $rawArray[1]);
    }

    public static function sendChannel(string $message, string $channel = null) {
        if (DISABLE_IRC) {
            return true;
        }
        if ($channel == null) {
            $channel = MOD_CHAN;
        }
        $curl = new Curl;
        $curl->setUseProxy(false)
            ->setMethod(CurlMethod::POST)
            ->setOption(CURLOPT_POSTFIELDS, $message)
            ->setOption(CURLOPT_HTTPHEADER, array('Content-Type: plain/text'));
        $url = IRC_HTTP_SOCKET_ADDRESS . 'irc_msg/' . urlencode($channel);
        return $curl->fetch($url);
    }
}
