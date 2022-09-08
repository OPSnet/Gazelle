<?php

namespace Gazelle\Util;

class Irc
{
    public static function sendMessage(string $target, string $message) {
        if (DISABLE_IRC) {
            return true;
        }
        $curl = new Curl;
        $curl->setUseProxy(false)
            ->setMethod(CurlMethod::POST)
            ->setOption(CURLOPT_POSTFIELDS, $message)
            ->setOption(CURLOPT_HTTPHEADER, array('Content-Type: plain/text'));
        $url = IRC_HTTP_SOCKET_ADDRESS . 'irc_msg/' . urlencode($target);
        return $curl->fetch($url);
    }
}
