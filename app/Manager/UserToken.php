<?php

namespace Gazelle\Manager;

use \Gazelle\Enum\UserTokenType;

class UserToken extends \Gazelle\BaseManager {
    use \Gazelle\Pg;

    public function create(UserTokenType $type, \Gazelle\User $user, ?string $value = null): \Gazelle\User\Token {
        $field = ['type',       'id_user'];
        $args  = [$type->value, $user->id()];
        if (!empty($value)) {
            $field[] = 'token';
            $args[]  = $value;
        }
        $placeholders = placeholders($field);
        if ($type->interval()) {
            $field[]       = 'expiry';
            $args[]        = $type->interval();
            $placeholders .= ', now() + ?::interval';
        }
        $fields = implode(', ', $field);
        return $this->findById(
            (int)$this->pg()->scalar("
                insert into user_token ($fields) values ($placeholders) returning id_user_token
                ", ...$args
            )
        );
    }

    public function createPasswordResetToken(\Gazelle\User $user): \Gazelle\User\Token {
        // expire any existing requests
        $this->pg()->prepared_query("
            update user_token set
                expiry = now()
            where id_user = ?
                and type = ?
            ", $user->id(), UserTokenType::password->value
        );
        $userToken = $this->create(UserTokenType::password, $user);
        (new \Gazelle\Util\Mail)->send($user->email(), 'Password reset information for ' . SITE_NAME,
            self::$twig->render('email/password-reset.twig', [
                'ipaddr'    => $_SERVER['REMOTE_ADDR'],
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
        $info = $this->pg()->rowAssoc("
            select id_user_token, id_user from user_token where id_user_token = ?
            ", $id
        );
        return $info
            ? new \Gazelle\User\Token($info['id_user_token'], new \Gazelle\User($info['id_user']))
            : null;
    }

    /**
     * Find a user token record based on its token value
     */
    public function findByToken(string $token): ?\Gazelle\User\Token {
        return $this->findById(
            (int)$this->pg()->scalar("
                select id_user_token
                from user_token
                where now() < expiry
                    and token = ?
                ", $token
            )
        );
    }

    /**
     * Find a user token record based on its token value
     */
    public function findByUser(\Gazelle\User $user, UserTokenType $type): ?\Gazelle\User\Token {
        return $this->findById(
            (int)$this->pg()->scalar("
                select id_user_token
                from user_token
                where id_user = ?
                    and type = ?
                ", $user->id(), $type->value
            )
        );
    }

    public function expireTokens(UserTokenType $type): int {
        return $this->pg()->prepared_query("
            delete from user_token
            where expiry < now()
                and type = ?
            ", $type->value
        );
    }

    public function removeUser(\Gazelle\User $user): int {
        return (int)$this->pg()->prepared_query("
            delete from user_token where id_user = ?
            ", $user->id()
        );
    }
}
