<?php

/** @phpstan-var \Gazelle\Debug $Debug */

declare(strict_types=1);

namespace Gazelle;

abstract class Error extends Base {
    public static function error(string $message = ''): never {
        echo static::payload($message);
        global $Debug;
        $Debug->storeMemory(memory_get_usage(true));
        $Debug->storeDuration($Debug->duration() * 1000000);
        exit;
    }

    public static function payload(string $message): string {
        header("{$_SERVER['SERVER_PROTOCOL']} " . static::errorCode() . ' ' . static::errorLabel());
        return static::$twig->render('error.twig', [
            'code'        => static::errorCode(),
            'label'       => static::errorLabel(),
            'description' => static::errorDescription(),
            'message'     => $message,
        ]);
    }

    abstract public static function errorCode(): int;

    abstract public static function errorLabel(): string;

    abstract public static function errorDescription(): string;
}
