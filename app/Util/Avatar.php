<?php

namespace Gazelle\Util;

class Avatar {
    protected int $size = 150;

    public function __construct(
        protected readonly int $mode
    ) {}

    public function setSize(int $size) {
        $this->size = $size;
        return $this;
    }

    public function avatar(string $username): string {
        $hash = md5(AVATAR_SALT . $username);
        return match($this->mode) {
            1 => "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=monsterid",
            2 => "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=wavatar",
            3 => "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=retro",
            4 => "https://robohash.org/$hash?set=set1&amp;size={$this->size}x{$this->size}",
            5 => "https://robohash.org/$hash?set=set2&amp;size={$this->size}x{$this->size}",
            6 => "https://robohash.org/$hash?set=set3&amp;size={$this->size}x{$this->size}",
            default => "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;d=identicon&amp;r=pg",
        };
    }
}
