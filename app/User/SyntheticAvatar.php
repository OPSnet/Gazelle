<?php

namespace Gazelle\User;

use Gazelle\Enum\AvatarSynthetic;

class SyntheticAvatar extends \Gazelle\BaseUser {
    final public const tableName = '';

    public function flush(): static {
        $this->user->flush();
        return $this;
    }

    public function avatar(string $username): string {
        $hash = md5(AVATAR_SALT . $username);
        $size = AVATAR_WIDTH;
        return match ((int)$this->user->option('Identicons')) {
            AvatarSynthetic::monster->value => "https://secure.gravatar.com/avatar/{$hash}?s={$size}&r=pg&d=monsterid",
            AvatarSynthetic::wavatar->value => "https://secure.gravatar.com/avatar/{$hash}?s={$size}&r=pg&d=wavatar",
            AvatarSynthetic::retro->value   => "https://secure.gravatar.com/avatar/{$hash}?s={$size}&r=pg&d=retro",
            AvatarSynthetic::robot1->value  => "https://robohash.org/{$hash}?set=set1&size={$size}x{$size}",
            AvatarSynthetic::robot2->value  => "https://robohash.org/{$hash}?set=set2&size={$size}x{$size}",
            AvatarSynthetic::robot3->value  => "https://robohash.org/{$hash}?set=set3&size={$size}x{$size}",
            default => "https://secure.gravatar.com/avatar/{$hash}?s={$size}&d=identicon&r=pg",
        };
    }
}
