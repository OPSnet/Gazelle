<?php

declare(strict_types=1);

namespace Gazelle\Search\Comment;

use Gazelle\User;

class Artist extends AbstractComment {
    public function __construct(
        public readonly string $filter,
        public readonly bool   $ownProfile,
        public readonly User   $user,
        public readonly int    $argOffset,
    ) {}

    public function table(): string {
        return 'relay.artists_group src inner join relay.artists_alias aa on (src."PrimaryAlias" = aa."AliasID")';
    }

    public function idField(): string {
        return '"ArtistID"';
    }

    public function nameField(): string {
        return 'aa."Name"';
    }

    public function condition(): array {
        return ['c."AuthorID" = $' . $this->argOffset];
    }

    public function args(): array {
        return [$this->user->id];
    }

    public function title(): string {
        return "Artist comments";
    }
}
