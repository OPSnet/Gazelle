<?php

declare(strict_types=1);

namespace Gazelle;

abstract class Error extends Base {
    public static function error(string $message = ''): never {
        echo static::payload($message);
        throw new Exception\EarlyExitException();
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

    public static function fromCode(int $errorCode): Error {
        /** @phpstan-var class-string<Error> $errorClass */
        $errorClass = "\\Gazelle\\Error{$errorCode}";
        if (class_exists($errorClass)) {
            return new $errorClass();
        }
        return new Error500();
    }

    abstract public static function errorCode(): int;

    abstract public static function errorLabel(): string;

    abstract public static function errorDescription(): string;
}
