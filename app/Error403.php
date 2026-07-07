<?php

declare(strict_types=1);

namespace Gazelle;

class Error403 extends Error {
    public static function error(string $message = ''): never {
        $ctx = static::$requestContext;
        $who = $ctx->hasViewer() ? $ctx->viewer()->label() : null;
        if ($who) {
            $ipaddr = $ctx->remoteAddr();
            $asn = new Search\ASN();
            Util\Irc::sendMessage(
                IRC_CHAN_STATUS,
                "$who ($ipaddr [{$asn->countryISO($ipaddr)}]) on {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}"
                . (!empty($_SERVER['HTTP_REFERER']) ? " from {$_SERVER['HTTP_REFERER']}" : '')
                . (!empty($message) ? " message '$message'" : '')
            );
        }
        parent::error($message);
    }

    public static function errorCode(): int {
        return 403;
    }

    public static function errorLabel(): string {
        return 'Forbidden';
    }

    public static function errorDescription(): string {
        return "You cannot view something you are not allowed to view.";
    }
}
