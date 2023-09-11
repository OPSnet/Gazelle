<?php

namespace Gazelle\User;

use \Gazelle\Enum\AvatarSynthetic;

class SyntheticAvatar extends \Gazelle\BaseUser {
    final const tableName = '';

    public function flush(): \Gazelle\User { return $this->user->flush(); }
    public function link(): string { return $this->user->link(); }
    public function location(): string { return $this->user->location(); }

    public function avatar(string $username): string {
        $hash = md5(AVATAR_SALT . $username);
        $size = AVATAR_WIDTH;
        return match((int)$this->user->option('Identicons')) {
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
