<?php

declare(strict_types=1);

namespace Gazelle\Search\Comment;

use Gazelle\User;

class Collage extends AbstractComment {
    public function __construct(
        public readonly string $filter,
        public readonly bool   $ownProfile,
        public readonly User   $user,
        public readonly int    $argOffset,
    ) {}

    public function table(): string {
        return 'relay.collages src';
    }

    public function idField(): string {
        return '"ID"';
    }

    public function nameField(): string {
        return 'src."Name"';
    }

    public function condition(): array {
        $placeholder = '$' . $this->argOffset;
        return [
            "src.\"Deleted\" = '0'",
            match ($this->filter) {
                'created'     => "src.\"UserID\" = $placeholder and c.\"AuthorID\" != $placeholder",
                'contributed' => <<<END_SQL
                    c."AuthorID" != $placeholder and src."ID" in (
                    select distinct "CollageID" from relay.collages_torrents ct where ct."UserID" = $placeholder
                    union all
                    select distinct "CollageID" from relay.collages_artists ca where ca."UserID" = $placeholder)
                END_SQL,
                default => "c.\"AuthorID\" = $placeholder",
            },
        ];
    }

    public function args(): array {
        return [$this->user->id];
    }

    public function title(): string {
        return match ($this->filter) {
            'created'     => 'Comments on ' . ($this->ownProfile ? 'your' : 'their') . ' collages',
            'contributed' => 'Contributed collage comments',
            default       => 'Collage comments',
        };
    }

    public function linkList(): array {
        return match ($this->filter) {
            'created' => [
                [
                    'link'  => '',
                    'title' => 'Collage comments'
                ],
                [
                    'link'  => '&amp;type=contributed',
                    'title' => 'Contributed collage comments'
                ],
            ],
            'contributed' => [
                [
                    'link'  => '',
                    'title' => 'Collage comments'
                ],
                [
                    'link'  => '&amp;type=created',
                    'title' => 'Comments on ' . ($this->ownProfile ? 'your' : 'their') . ' collages'
                ],
            ],
            default => [
                [
                    'link'  => '&amp;type=contributed',
                    'title' => 'Contributed collage comments'
                ],
                [
                    'link'  => '&amp;type=created',
                    'title' => 'Comments on ' . ($this->ownProfile ? 'your' : 'their') . ' collages'
                ],
            ],
        };
    }
}
