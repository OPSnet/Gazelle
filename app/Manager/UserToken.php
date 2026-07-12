<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\Enum\UserTokenType;
use Gazelle\User;

class UserToken extends \Gazelle\BaseManager {
    public function create(UserTokenType $type, User $user, string|null $value = null): \Gazelle\User\Token {
        $field       = ['type',       'id_user'];
        $placeholder = ['$1',         '$2'];
        $args        = [$type->value, $user->id];
        if (isset($value)) {
            $field[]       = 'token';
            $placeholder[] = '$3';
            $args[]        = $value;
        }
        if ($type->hourInterval()) {
            $field[]       = "expiry";
            $placeholder[] = "now() + interval '1 hour' * {$this->pg()->placeholder(count($args))}";
            $args[]        = $type->hourInterval();
        }
        return $this->findById(
            $this->pg()->scalarex(
                sprintf(
                    'insert into user_token (%s) values (%s) returning id_user_token',
                    implode(', ', $field),
                    implode(', ', $placeholder),
                ), ...$args
            )
        );
    }

    public function createPasswordResetToken(User $user): \Gazelle\User\Token {
        // expire any existing requests
        $this->pg()->query("
            update user_token set
                expiry = now()
            where id_user = $1
                and type = $2
            ", $user->id, UserTokenType::password->value
        );
        $userToken = $this->create(UserTokenType::password, $user);
        new \Gazelle\Util\Mail()->send($user->email(), 'Password reset information for ' . SITE_NAME,
            self::$twig->render('email/password-reset.text.twig', [
                'ipaddr'    => $this->requestContext()->remoteAddr(),
                'reset_key' => $userToken->value(),
                'user'      => $user,
            ])
        );
        return $userToken;
    }

    /**
     * Get a user token based on its internal ID
     * Normally you should never need this, all tokens are found by their token instance
     */
    public function findById(int $id): ?\Gazelle\User\Token {
         $token = $this->pg()->rowAssocex("
            select id_user_token,
                id_user
            from user_token
            where id_user_token = $1
            ", $id
        );
        if ($token === []) {
            return null;
        }
        return new \Gazelle\User\Token(
            $token['id_user_token'],
            new User($token['id_user'])
        );
    }

    /**
     * Find a user token record based on its token value
     */
    public function findByToken(string $token): ?\Gazelle\User\Token {
        return $this->findById(
            (int)$this->pg()->scalarex("
                select id_user_token
                from user_token
                where now() < expiry
                    and token = $1
                ", $token
            )
        );
    }

    /**
     * Find a user token record based on its token value
     */
    public function findByUser(User $user, UserTokenType $type): ?\Gazelle\User\Token {
        return $this->findById(
            (int)$this->pg()->scalarex("
                select id_user_token
                from user_token
                where id_user = $1
                    and type = $2
                ", $user->id, $type->value
            )
        );
    }

    public function expireTokens(UserTokenType $type): int {
        return $this->pg()->query("
            delete from user_token
            where expiry < now()
                and type = $1
            ", $type->value
        );
    }

    public function removeTokens(User $user, UserTokenType $type): int {
        return $this->pg()->query("
            delete from user_token
            where id_user = $1
                and type = $2
            ", $user->id, $type->value
        );
    }

    public function removeUser(User $user): int {
        return $this->pg()->query("
            delete from user_token where id_user = $1
            ", $user->id
        );
    }
}
